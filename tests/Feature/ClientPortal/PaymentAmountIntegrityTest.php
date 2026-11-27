<?php

namespace Tests\Feature\ClientPortal;

use App\Factory\InvoiceItemFactory;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Services\ClientPortal\LivewireInstantPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class PaymentAmountIntegrityTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->actingAs($this->contact, 'contact');
        $this->setPaymentSettings();
    }

    #[DataProvider('unapprovedPaymentAmounts')]
    public function testPaymentInitiationRejectsUnapprovedAmounts(float $partial, float $submitted_amount): void
    {
        $invoice = $this->prepareInvoice(7055.87, 7055.87, $partial);
        $company_gateway = $this->makeGateway();
        $hash_count = PaymentHash::count();

        $response = (new LivewireInstantPayment($this->paymentData($invoice, $company_gateway, $submitted_amount)))->run();

        $this->assertFalse($response['success'], 'Disabled underpayments and overpayments must be enforced when the payment hash is created.');
        $this->assertSame($hash_count, PaymentHash::count());
    }

    public static function unapprovedPaymentAmounts(): array
    {
        return [
            'overpayment' => [0, 9779.71],
            'underpayment' => [0, 6000],
            'payment above requested deposit' => [1000, 7055.87],
        ];
    }

    public function testZeroFeeCheckoutPreservesTaxedInvoice(): void
    {
        $invoice = $this->prepareTaxedInvoice(10);
        $company_gateway = $this->makeGateway();
        $original_attributes = $invoice->getRawOriginal();

        $response = (new LivewireInstantPayment($this->paymentData($invoice, $company_gateway, 110)))->run();

        $this->assertTrue($response['success']);
        $this->assertEqualsWithDelta(0, (float) data_get($response, 'payload.total.fee_total'), 0.001);
        $this->assertEqualsWithDelta(110, (float) data_get($response, 'payload.total.amount_with_fee'), 0.001);
        $this->assertSame($original_attributes, $invoice->fresh()->getRawOriginal());
    }

    #[DataProvider('invoiceTaxRates')]
    public function testGatewayFeeQuoteExcludesInvoiceTaxes(float $tax_rate, float $expected_amount): void
    {
        $invoice = $this->prepareTaxedInvoice($tax_rate);
        $company_gateway = $this->makeGateway(fee_amount: 5);
        $original_attributes = $invoice->getRawOriginal();

        $this->assertEqualsWithDelta($expected_amount, (float) $invoice->amount, 0.001);
        $this->assertEqualsWithDelta($expected_amount, (float) $invoice->balance, 0.001);
        $this->assertEqualsWithDelta($expected_amount - 100, (float) $invoice->total_taxes, 0.001);

        $quote = $invoice->service()->quoteGatewayFee($company_gateway, GatewayType::CREDIT_CARD, $expected_amount);

        $this->assertSame($original_attributes, $invoice->fresh()->getRawOriginal(), 'Quoting a fee must not change the invoice.');
        $this->assertEqualsWithDelta(5, $quote['net'], 0.001);
        $this->assertEqualsWithDelta(5, $quote['gross'], 0.001,
            'Invoice taxes must not be counted as part of an untaxed gateway fee.');
    }

    public static function invoiceTaxRates(): array
    {
        return [
            '6% invoice tax' => [6, 106],
            '14% invoice tax' => [14, 114],
        ];
    }

    #[DataProvider('changedPayableAmounts')]
    public function testPaymentInitiationRejectsStaleAmountAfterInvoiceChanges(float $balance, float $partial): void
    {
        $invoice = $this->prepareInvoice(100, 100);
        $company_gateway = $this->makeGateway();
        $payment_data = $this->paymentData($invoice, $company_gateway, 100);
        $hash_count = PaymentHash::count();

        $invoice->balance = $balance;
        $invoice->partial = $partial;
        $invoice->paid_to_date = 100 - $balance;
        $invoice->status_id = $balance < 100 ? Invoice::STATUS_PARTIAL : Invoice::STATUS_SENT;
        $invoice->saveQuietly();

        $response = (new LivewireInstantPayment($payment_data))->run();

        $this->assertFalse($response['success'], 'A stale checkout amount must be revalidated against the current balance or partial.');
        $this->assertSame($hash_count, PaymentHash::count());
    }

    public static function changedPayableAmounts(): array
    {
        return [
            'another payment reduced the balance' => [60, 0],
            'a deposit was requested after checkout opened' => [100, 25],
        ];
    }

    #[DataProvider('validPaymentAmounts')]
    public function testPaymentInitiationPreservesLegitimatePaymentAmounts(
        float $balance,
        float $partial,
        float $submitted_amount,
        bool $allow_under_payment,
        bool $allow_over_payment,
    ): void {
        $this->setPaymentSettings($allow_under_payment, $allow_over_payment);
        $invoice = $this->prepareInvoice(100, $balance, $partial);
        $company_gateway = $this->makeGateway();

        $response = (new LivewireInstantPayment($this->paymentData($invoice, $company_gateway, $submitted_amount)))->run();

        $this->assertTrue($response['success']);
        $payment_hash = $response['payload']['ph']->fresh();
        $this->assertEqualsWithDelta($submitted_amount, (float) data_get($payment_hash->data, 'invoices.0.amount'), 0.001);
        $this->assertEqualsWithDelta($submitted_amount, (float) $payment_hash->amount_with_fee(), 0.001);
        $this->assertEqualsWithDelta(0, (float) $payment_hash->fee_total, 0.001);
        $this->assertEqualsWithDelta(100, (float) $invoice->fresh()->amount, 0.001);
        $this->assertEqualsWithDelta($balance, (float) $invoice->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta(100 - $balance, (float) $invoice->fresh()->paid_to_date, 0.001);
        $this->assertEqualsWithDelta($partial, (float) $invoice->fresh()->partial, 0.001);
    }

    public static function validPaymentAmounts(): array
    {
        return [
            'full invoice' => [100, 0, 100, false, false],
            'remaining balance after payment' => [60, 0, 60, false, false],
            'requested deposit' => [100, 25, 25, false, false],
            'explicitly allowed underpayment' => [100, 0, 20, true, false],
            'explicitly allowed overpayment' => [100, 0, 125, false, true],
        ];
    }

    private function setPaymentSettings(bool $allow_under_payment = false, bool $allow_over_payment = false): void
    {
        $settings = $this->client->settings;
        $settings->client_portal_allow_under_payment = $allow_under_payment;
        $settings->client_portal_allow_over_payment = $allow_over_payment;
        $settings->client_portal_under_payment_minimum = 1;
        $settings->use_credits_payment = 'off';
        $this->client->settings = $settings;
        $this->client->saveQuietly();
    }

    private function prepareInvoice(float $amount, float $balance, float $partial = 0): Invoice
    {
        $line_item = InvoiceItemFactory::create();
        $line_item->quantity = 1;
        $line_item->cost = $amount;

        $this->invoice->forceFill([
            'line_items' => [$line_item],
            'amount' => $amount,
            'balance' => $balance,
            'partial' => $partial,
            'paid_to_date' => $amount - $balance,
            'total_taxes' => 0,
            'discount' => 0,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'uses_inclusive_taxes' => false,
            'status_id' => $balance < $amount ? Invoice::STATUS_PARTIAL : Invoice::STATUS_SENT,
        ]);

        return $this->invoice->calc()->getInvoice()->fresh();
    }

    private function prepareTaxedInvoice(float $tax_rate): Invoice
    {
        $invoice = $this->prepareInvoice(100, 100);
        $line_items = $invoice->line_items;
        $line_items[0]->tax_name1 = 'Sales tax';
        $line_items[0]->tax_rate1 = $tax_rate;
        $invoice->line_items = $line_items;

        return $invoice->calc()->getInvoice()->fresh();
    }

    private function makeGateway(float $fee_amount = 0): CompanyGateway
    {
        $company_gateway = new CompanyGateway();
        $company_gateway->company_id = $this->company->id;
        $company_gateway->user_id = $this->user->id;
        $company_gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $company_gateway->config = encrypt(json_encode(new \stdClass()));
        $company_gateway->fees_and_limits = [
            GatewayType::CREDIT_CARD => [
                'min_limit' => -1,
                'max_limit' => -1,
                'fee_amount' => $fee_amount,
                'fee_percent' => 0,
                'fee_tax_name1' => '',
                'fee_tax_rate1' => 0,
                'fee_tax_name2' => '',
                'fee_tax_rate2' => 0,
                'fee_tax_name3' => '',
                'fee_tax_rate3' => 0,
                'fee_cap' => 0,
                'adjust_fee_percent' => false,
                'is_enabled' => true,
            ],
        ];
        $company_gateway->save();

        return $company_gateway;
    }

    private function paymentData(Invoice $invoice, CompanyGateway $company_gateway, float $amount): array
    {
        return [
            'company_gateway_id' => $company_gateway->id,
            'payment_method_id' => GatewayType::CREDIT_CARD,
            'payable_invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => $amount]],
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
        ];
    }
}
