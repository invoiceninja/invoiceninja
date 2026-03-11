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
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class InvoicePaidToDateValidationTest extends TestCase
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
    }

    /**
     * Helper: create an invoice with a given amount, mark it sent,
     * and return the fresh model.
     */
    private function createSentInvoice(float $cost = 100): Invoice
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;
        $item->type_id = '1';

        $data = [
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'client_id' => $this->client->hashed_id,
            'line_items' => [$item],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true', $data)
            ->assertStatus(200);

        $arr = $response->json();

        return Invoice::find($this->decodePrimaryKey($arr['data']['id']));
    }

    /**
     * Update succeeds when paid_to_date matches the DB value (no payment applied).
     */
    public function testUpdateSucceedsWhenPaidToDateUnchanged()
    {
        $invoice = $this->createSentInvoice(100);

        $this->assertEquals(0, $invoice->paid_to_date);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 0,
            'public_notes' => 'Updated notes',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated notes', $response->json('data.public_notes'));
    }

    /**
     * Update fails (422) when a payment was applied between load and save,
     * causing paid_to_date to drift from the client's snapshot.
     */
    public function testUpdateFailsWhenPaymentAppliedBetweenEdits()
    {
        $invoice = $this->createSentInvoice(100);

        // Client loads invoice — snapshots paid_to_date as 0
        $snapshot_paid_to_date = $invoice->paid_to_date;
        $this->assertEquals(0, $snapshot_paid_to_date);

        // Meanwhile a payment is applied
        $invoice->service()->applyPaymentAmount(50, 'concurrent payment')->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(50, $invoice->paid_to_date);

        // Client tries to save with stale paid_to_date
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => $snapshot_paid_to_date,
            'public_notes' => 'Stale edit',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }

    /**
     * Update succeeds when the client's paid_to_date snapshot matches
     * after a payment has been applied (i.e. client refreshed first).
     */
    public function testUpdateSucceedsAfterRefreshingPostPayment()
    {
        $invoice = $this->createSentInvoice(100);

        // Payment applied
        $invoice->service()->applyPaymentAmount(30, 'test payment')->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(30, $invoice->paid_to_date);

        // Client refreshes, sends correct paid_to_date
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 30,
            'public_notes' => 'Fresh edit after payment',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Fresh edit after payment', $response->json('data.public_notes'));
    }

    /**
     * Validation catches a full payment (invoice fully paid) during edit.
     */
    public function testUpdateFailsWhenInvoiceFullyPaidDuringEdit()
    {
        $invoice = $this->createSentInvoice(100);

        $snapshot_paid_to_date = $invoice->paid_to_date;

        // Full payment applied while client edits
        $invoice->service()->applyPaymentAmount(100, 'full payment')->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => $snapshot_paid_to_date,
            'public_notes' => 'Should not save',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }

    /**
     * Validation catches partial payment applied, then another partial payment,
     * between client load and save. Client snapshot is from before both.
     */
    public function testUpdateFailsWithMultiplePaymentsBetweenEdits()
    {
        $invoice = $this->createSentInvoice(100);

        $snapshot_paid_to_date = $invoice->paid_to_date;

        // Two payments applied between load and save
        $invoice->service()->applyPaymentAmount(25, 'payment 1')->save();
        $invoice = $invoice->fresh();
        $invoice->service()->applyPaymentAmount(25, 'payment 2')->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(50, $invoice->paid_to_date);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => $snapshot_paid_to_date,
            'public_notes' => 'Stale after two payments',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }

    /**
     * When paid_to_date is omitted from the request entirely,
     * the validation rule should not fire (field is not present).
     */
    public function testUpdateSucceedsWhenPaidToDateOmitted()
    {
        $invoice = $this->createSentInvoice(100);

        // Payment applied but client doesn't send paid_to_date at all
        $invoice->service()->applyPaymentAmount(50, 'some payment')->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'public_notes' => 'Edit without paid_to_date field',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Fractional cent values: BcMath comparison catches small differences.
     */
    public function testUpdateFailsOnFractionalCentDifference()
    {
        $invoice = $this->createSentInvoice(100);

        $invoice->service()->applyPaymentAmount(33.33, 'fractional payment')->save();
        $invoice = $invoice->fresh();

        $this->assertEqualsWithDelta(33.33, $invoice->paid_to_date, 0.01);

        // Client sends a rounded or slightly wrong value
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 33.34,
            'public_notes' => 'off by a penny',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }

    /**
     * Exact fractional match passes.
     */
    public function testUpdateSucceedsWithExactFractionalMatch()
    {
        $invoice = $this->createSentInvoice(100);

        $invoice->service()->applyPaymentAmount(33.33, 'fractional payment')->save();
        $invoice = $invoice->fresh();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => $invoice->paid_to_date,
            'public_notes' => 'exact match',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Client sends paid_to_date as zero on an invoice that has never had
     * a payment — should pass since DB is also zero.
     */
    public function testUpdateSucceedsWithZeroPaidToDateOnUnpaidInvoice()
    {
        $invoice = $this->createSentInvoice(100);

        $this->assertEquals(0, $invoice->paid_to_date);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 0,
            'tax_name1' => 'GST',
            'tax_rate1' => 10,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Client sends paid_to_date as string "0" — should still match
     * a numeric zero in DB (BcMath handles type coercion).
     */
    public function testUpdateHandlesStringZeroPaidToDate()
    {
        $invoice = $this->createSentInvoice(100);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => '0',
            'public_notes' => 'string zero',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Client sends paid_to_date as a string number that matches DB.
     */
    public function testUpdateHandlesStringNumericPaidToDate()
    {
        $invoice = $this->createSentInvoice(100);

        $invoice->service()->applyPaymentAmount(50, 'test')->save();
        $invoice = $invoice->fresh();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => '50',
            'public_notes' => 'string fifty',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Large invoice amount: payment applied between edits is still caught.
     */
    public function testUpdateFailsOnLargeInvoiceWithPaymentDrift()
    {
        $invoice = $this->createSentInvoice(999999.99);

        $snapshot_paid_to_date = $invoice->paid_to_date;

        $invoice->service()->applyPaymentAmount(500000, 'large partial')->save();
        $invoice = $invoice->fresh();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => $snapshot_paid_to_date,
            'public_notes' => 'stale on large invoice',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }

    /**
     * Negative paid_to_date from client should fail (DB has 0).
     */
    public function testUpdateFailsWithNegativePaidToDate()
    {
        $invoice = $this->createSentInvoice(100);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => -10,
            'public_notes' => 'negative paid_to_date',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }

    /**
     * Client fabricates a paid_to_date higher than what's in the DB.
     */
    public function testUpdateFailsWhenClientInflatesPaidToDate()
    {
        $invoice = $this->createSentInvoice(100);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 999,
            'public_notes' => 'inflated paid_to_date',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }

    /**
     * Sequential edits both succeed when no payment activity occurs.
     */
    public function testMultipleEditsSucceedWithoutPaymentActivity()
    {
        $invoice = $this->createSentInvoice(100);

        // First edit
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 0,
            'public_notes' => 'first edit',
        ]);

        $response->assertStatus(200);

        // Second edit
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 0,
            'public_notes' => 'second edit',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('second edit', $response->json('data.public_notes'));
    }

    /**
     * Null paid_to_date is treated as zero by BcMath — passes on unpaid invoice.
     */
    public function testUpdateSucceedsWithNullPaidToDateOnUnpaidInvoice()
    {
        $invoice = $this->createSentInvoice(100);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => null,
            'public_notes' => 'null paid_to_date',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Null paid_to_date bypasses the closure due to the `nullable` rule —
     * same behavior as omitting the field entirely. The closure never fires.
     * This means null does NOT act as a concurrency check.
     */
    public function testUpdatePassesWithNullPaidToDateOnPaidInvoice()
    {
        $invoice = $this->createSentInvoice(100);

        $invoice->service()->applyPaymentAmount(50, 'test payment')->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(50, $invoice->paid_to_date);

        // null bypasses the closure — same as omitting paid_to_date
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => null,
            'public_notes' => 'null on paid invoice',
        ]);

        $response->assertStatus(200);
    }

    /**
     * First edit succeeds, payment applied, second edit (with stale snapshot) fails.
     */
    public function testSecondEditFailsAfterInterleavedPayment()
    {
        $invoice = $this->createSentInvoice(100);

        // First edit succeeds
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 0,
            'public_notes' => 'first edit',
        ]);

        $response->assertStatus(200);

        // Payment applied
        $invoice = $invoice->fresh();
        $invoice->service()->applyPaymentAmount(20, 'interleaved')->save();

        // Second edit with stale snapshot fails
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $invoice->hashed_id, [
            'paid_to_date' => 0,
            'public_notes' => 'second edit stale',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_to_date');
    }
}
