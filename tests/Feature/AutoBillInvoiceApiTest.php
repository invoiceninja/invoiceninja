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

    /**
     * POST /api/v1/invoices?auto_bill=true  (without mark_sent)
     *
     * Thesis: when auto_bill=true is used on a draft invoice (without
     * mark_sent=true), a manual payment is applied because the invoice
     * starts in draft status.
     *
     * Actual behavior: AutoBillInvoice::run() calls markSent() first
     * (line 62), transitioning the draft to SENT and setting balance =
     * amount. Then it tries to collect via credits, unapplied payments,
     * and gateway tokens. Without a payment source, the exception is
     * caught silently and the invoice remains SENT/unpaid — the user's
     * draft invoice was promoted to SENT as a side effect of auto_bill.
     */
    public function testAutoBillDraftInvoiceCreatesManualPayment(): void
    {
        $settings = ClientSettings::defaults();
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';

        $this->client->settings = $settings;
        $this->client->save();

        $payment_count_before = Payment::where('client_id', $this->client->id)->count();

        // Prove that a plain draft has balance=0 even though amount > 0
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices', $this->invoiceData(100));

        $response->assertStatus(200);
        $arr = $response->json();
        $draft = Invoice::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(Invoice::STATUS_DRAFT, $draft->status_id);
        $this->assertEquals(100, (float) $draft->amount, 'Draft amount is calculated');
        $this->assertEquals(0, (float) $draft->balance, 'Draft balance is 0 (not calculated for drafts)');

        // Now create with auto_bill=true but WITHOUT mark_sent=true
        $response2 = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?auto_bill=true', $this->invoiceData(100));

        $response2->assertStatus(200);
        $arr2 = $response2->json();
        $invoice = Invoice::find($this->decodePrimaryKey($arr2['data']['id']));

        // Side effect: AutoBillInvoice::run() calls markSent() before
        // attempting to collect payment, so the draft is promoted to SENT
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id, 'Draft was promoted to SENT by auto_bill');
        $this->assertEquals(100, (float) $invoice->balance, 'Balance equals amount after markSent');
        $this->assertEquals(0, (float) $invoice->paid_to_date, 'No payment was collected');

        // No payment was created — the auto_bill failed silently because
        // there is no gateway token, credits, or unapplied payments
        $payment = Payment::query()
            ->whereHas('invoices', fn ($q) => $q->where('invoices.id', $invoice->id))
            ->first();

        $this->assertNull($payment, 'No payment linked to this invoice');

        $payment_count_after = Payment::where('client_id', $this->client->id)->count();
        $this->assertEquals($payment_count_before, $payment_count_after, 'No new payments created');
    }

    /**
     * Guards against a regression where a draft invoice (balance=0 because
     * balance is not computed until markSent) was incorrectly marked as paid.
     *
     * The fix: AutoBillInvoice::run() must call markSent() BEFORE checking
     * for zero balance, so the balance is properly calculated first.
     */
    public function testAutoBillDraftDoesNotMarkPaidDueToZeroBalance(): void
    {
        $settings = ClientSettings::defaults();
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';

        $this->client->settings = $settings;
        $this->client->save();

        // Create a draft invoice via API (no mark_sent, no auto_bill)
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices', $this->invoiceData(250));

        $response->assertStatus(200);
        $arr = $response->json();
        $invoice = Invoice::find($this->decodePrimaryKey($arr['data']['id']));

        // Precondition: draft has balance=0 but amount=250
        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status_id);
        $this->assertEquals(0, (float) $invoice->balance);
        $this->assertEquals(250, (float) $invoice->amount);

        // Now auto-bill this draft directly — no gateway token exists so
        // the auto-bill will throw after markSent, which is expected
        try {
            $invoice->service()->autoBill();
        } catch (\Exception $e) {
            // Expected: no payment method available
        }

        $invoice = $invoice->fresh();

        // The invoice must NOT be marked as paid — it has a real amount owing
        $this->assertNotEquals(Invoice::STATUS_PAID, $invoice->status_id, 'Draft with amount > 0 must not be marked paid just because draft balance is 0');
        $this->assertEquals(250, (float) $invoice->balance, 'Balance should equal amount after markSent');
        $this->assertEquals(0, (float) $invoice->paid_to_date, 'No payment should have been applied');
    }

    /**
     * A genuinely zero-amount invoice (line items sum to 0) should be
     * correctly marked as paid when auto-billed.
     */
    public function testAutoBillZeroAmountInvoiceIsMarkedPaid(): void
    {
        $settings = ClientSettings::defaults();
        $settings->use_credits_payment = 'off';
        $settings->use_unapplied_payment = 'off';

        $this->client->settings = $settings;
        $this->client->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true', $this->invoiceData(0));

        $response->assertStatus(200);
        $arr = $response->json();
        $invoice = Invoice::find($this->decodePrimaryKey($arr['data']['id']));

        // Precondition: sent invoice with zero amount and zero balance
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertEquals(0, (float) $invoice->balance);
        $this->assertEquals(0, (float) $invoice->amount);

        // Auto-bill the zero-amount invoice
        $invoice->service()->autoBill();
        $invoice = $invoice->fresh();

        // A zero-amount invoice should be marked as paid
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id, 'Zero-amount invoice should be marked paid');
        $this->assertEquals(0, (float) $invoice->balance);
    }
}
