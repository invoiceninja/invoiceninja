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
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\DataMapper\ClientSettings;
use App\Factory\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

class AutoBillInvoiceApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    private function invoiceData(float $cost = 100): array
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;
        $item->type_id = '1';

        return [
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'client_id' => $this->client->hashed_id,
            'line_items' => [$item],
        ];
    }

    /**
     * POST /api/v1/invoices?mark_sent=true&auto_bill=true
     *
     * When a client has credits on file, auto_bill should consume the
     * credits and create a credit-type payment — not a manual payment
     * via markPaid().
     */
    public function testAutoBillWithCreditsDoesNotCreateManualPayment(): void
    {
        $settings = ClientSettings::defaults();
        $settings->use_credits_payment = 'always';
        $settings->use_unapplied_payment = 'off';

        $this->client->settings = $settings;
        $this->client->save();

        $credit = Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'balance' => 200,
            'amount' => 200,
            'discount' => 0,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'number' => 'CREDIT-AUTO-BILL-TEST',
            'status_id' => Credit::STATUS_SENT,
            'is_deleted' => false,
            'due_date' => null,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true&auto_bill=true', $this->invoiceData(100));

        $response->assertStatus(200);

        $arr = $response->json();
        $invoice = Invoice::find($this->decodePrimaryKey($arr['data']['id']));

        // Invoice should be fully paid
        $this->assertEquals(0, (int) $invoice->balance);
        $this->assertEquals($invoice->amount, $invoice->paid_to_date);

        // The payment linked to this invoice must not be a manual payment
        $payment = Payment::query()
            ->whereHas('invoices', function ($q) use ($invoice) {
                $q->where('invoices.id', $invoice->id);
            })
            ->first();

        $this->assertNotNull($payment, 'A payment should be linked to the invoice');
        $this->assertFalse((bool) $payment->is_manual, 'auto_bill should not create a manual payment');

        // Credits should have been consumed
        $credit = $credit->fresh();
        $this->assertLessThan(200, $credit->balance, 'Credit balance should be reduced');
    }

    /**
     * POST /api/v1/invoices?mark_sent=true&auto_bill=true
     *
     * When credits and unapplied payments are disabled and no gateway
     * token exists, auto_bill should fail gracefully — the invoice
     * stays sent/unpaid with no payment created.
     */
    public function testAutoBillWithNoPaymentSourceLeavesInvoiceUnpaid(): void
    {
        $settings = ClientSettings::defaults();
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';

        $this->client->settings = $settings;
        $this->client->save();

        $payment_count_before = Payment::where('client_id', $this->client->id)->count();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true&auto_bill=true', $this->invoiceData(100));

        $response->assertStatus(200);

        $arr = $response->json();
        $invoice = Invoice::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertGreaterThan(0, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);

        $payment_count_after = Payment::where('client_id', $this->client->id)->count();
        $this->assertEquals($payment_count_before, $payment_count_after, 'No payment should be created when no payment source is available');
    }
}
