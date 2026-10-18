<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GatewayType;
use Tests\MockAccountData;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\PaymentHash;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * A negative fee_amount is a gateway DISCOUNT - it reduces the invoice balance.
 *
 * The client balance must move in the same direction and by the same magnitude
 * as the invoice balance, otherwise the client ledger diverges from the invoices
 * that produced it.
 *
 * @see \App\Services\Invoice\ConfirmGatewayFee
 */
class GatewayFeeDiscountSignTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    private function makeCompanyGateway(float $fee_amount): CompanyGateway
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = $fee_amount;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe') ?? '{}');
        $cg->fees_and_limits = $data;
        $cg->save();

        return $cg;
    }

    /**
     * Quotes the fee and confirms it, the way a completed payment does.
     */
    private function quoteAndConfirm(CompanyGateway $cg, Invoice $invoice, float $amount): void
    {
        $quote = $invoice->service()->quoteGatewayFee($cg, GatewayType::CREDIT_CARD, $amount);

        $payment_hash = PaymentHash::create([
            'hash' => Str::random(32),
            'fee_total' => $quote['gross'],
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'invoices' => [],
                'credits' => 0,
                'fee_net' => $quote['net'],
                'amount_with_fee' => round($amount + $quote['gross'], 2),
            ],
        ]);

        (new \App\Services\Invoice\ConfirmGatewayFee($payment_hash, $cg, ['gateway_type_id' => GatewayType::CREDIT_CARD]))->run();
    }

    /**
     * A negative fee_amount is reachable: calcGatewayFee() applies fee_amount with
     * no floor at zero, and fee_cap only clamps upward.
     */
    public function testNegativeFeeAmountProducesANegativeGatewayFee(): void
    {
        $cg = $this->makeCompanyGateway(-1.00);

        $this->assertEquals(-1.00, (float) $cg->calcGatewayFee(100, GatewayType::CREDIT_CARD, false));
    }

    /**
     * Control: the positive-fee branch keeps the invoice and the client in step.
     */
    public function testPositiveGatewayFeeMovesInvoiceAndClientTogether(): void
    {
        $cg = $this->makeCompanyGateway(1.00);

        $invoice = $this->invoice->service()->markSent()->save();

        $invoice_before = (float) $invoice->fresh()->balance;
        $client_before = (float) $invoice->client->fresh()->balance;

        $this->quoteAndConfirm($cg, $invoice, $invoice_before);

        $invoice_delta = (float) $invoice->fresh()->balance - $invoice_before;
        $client_delta = (float) $invoice->client->fresh()->balance - $client_before;

        $this->assertEqualsWithDelta(1.00, $invoice_delta, 0.001, 'a positive fee must raise the invoice balance');
        $this->assertEqualsWithDelta($invoice_delta, $client_delta, 0.001, 'client balance must track the invoice balance');
    }

    /**
     * The old processGatewayDiscount() applied $adjustment * -1 to the ledger where
     * $adjustment was already negative, so the client balance moved OPPOSITE to the
     * invoice. ConfirmGatewayFee posts one adjustment with one sign to both.
     */
    public function testGatewayDiscountMovesInvoiceAndClientTogether(): void
    {
        $cg = $this->makeCompanyGateway(-1.00);

        $invoice = $this->invoice->service()->markSent()->save();

        $invoice_before = (float) $invoice->fresh()->balance;
        $client_before = (float) $invoice->client->fresh()->balance;

        $this->quoteAndConfirm($cg, $invoice, $invoice_before);

        $invoice_delta = (float) $invoice->fresh()->balance - $invoice_before;
        $client_delta = (float) $invoice->client->fresh()->balance - $client_before;

        $this->assertEqualsWithDelta(-1.00, $invoice_delta, 0.001, 'a discount must reduce the invoice balance');

        $this->assertEqualsWithDelta(
            $invoice_delta,
            $client_delta,
            0.001,
            "client balance moved {$client_delta} while the invoice moved {$invoice_delta} - the discount is applied to the client with the wrong sign"
        );
    }
}
