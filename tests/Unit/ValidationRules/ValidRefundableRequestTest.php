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

namespace Tests\Unit\ValidationRules;

use App\Http\ValidationRules\Payment\ValidRefundableRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Reproduces a real-world refund rejection caused by floating point
 * arithmetic in ValidRefundableRequest::checkTotalRefundableAmount()
 * and ::checkInvoice().
 *
 * A payment of 30.20 fully applied across two invoices of 18.35 and
 * 11.85 could not be refunded in full: summing 18.35 + 11.85 as native
 * PHP floats yields 30.200000000000003, which then fails a strict `>`
 * comparison against the 30.20 refundable maximum.
 */
class ValidRefundableRequestTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->makeTestData();
    }

    public function testFullRefundOfPaymentSplitAcrossTwoInvoicesIsNotRejectedByFloatDrift()
    {
        $invoice_a = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status_id' => Invoice::STATUS_PAID,
            'amount' => 18.35,
            'balance' => 0,
            'paid_to_date' => 18.35,
            'date' => now(),
            'due_date' => now(),
        ]);

        $invoice_b = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status_id' => Invoice::STATUS_PAID,
            'amount' => 11.85,
            'balance' => 0,
            'paid_to_date' => 11.85,
            'date' => now(),
            'due_date' => now(),
        ]);

        $payment = Payment::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 30.20,
            'applied' => 30.20,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => now(),
        ]);

        $payment->invoices()->attach($invoice_a->id, ['amount' => 18.35, 'refunded' => 0]);
        $payment->invoices()->attach($invoice_b->id, ['amount' => 11.85, 'refunded' => 0]);

        $input = [
            'id' => $payment->id,
            'amount' => 30.20,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                ['invoice_id' => $invoice_a->id, 'amount' => 18.35],
                ['invoice_id' => $invoice_b->id, 'amount' => 11.85],
            ],
        ];

        request()->merge($input);

        $rule = new ValidRefundableRequest($input);

        $this->assertTrue($rule->passes('id', $payment->id), $rule->message());
    }

    public function testPartialRefundThatExceedsRemainingBalanceIsStillRejected()
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status_id' => Invoice::STATUS_PAID,
            'amount' => 30.20,
            'balance' => 0,
            'paid_to_date' => 30.20,
            'date' => now(),
            'due_date' => now(),
        ]);

        $payment = Payment::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 30.20,
            'applied' => 30.20,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => now(),
        ]);

        $payment->invoices()->attach($invoice->id, ['amount' => 30.20, 'refunded' => 0]);

        $input = [
            'id' => $payment->id,
            'amount' => 30.21,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                ['invoice_id' => $invoice->id, 'amount' => 30.21],
            ],
        ];

        request()->merge($input);

        $rule = new ValidRefundableRequest($input);

        $this->assertFalse($rule->passes('id', $payment->id));
    }
}
