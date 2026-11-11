<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://www.invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\PaymentDrivers\ChipInAsia;

use App\Exceptions\PaymentFailed;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\ChipInAsia\Hosted;
use App\PaymentDrivers\ChipInAsiaPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Tests for the status-handling in Hosted::createPaymentFromCallback
 * and the webhook handler's pending->completed transition.
 *
 * Per reviewer feedback, a 2xx HTTP response from CHIP's /charge/
 * endpoint does NOT necessarily mean the charge is settled — the
 * response body can carry status='pending_charge' when the acquirer
 * has not yet confirmed. The Payment record's status must match the
 * chip payload's status, and subsequent webhooks (e.g. purchase.paid
 * or purchase.payment_failure) must drive the transitions.
 */
class CreatePaymentStatusHandlingTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    /**
     * When the chip payload's status is 'paid', the Payment record
     * must be created with STATUS_COMPLETED. This is the existing
     * happy path and must keep working.
     */
    public function testCreatePaymentFromCallbackMarksPaidAsCompleted(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_paid_001',
            'status' => 'paid',
            'purchase' => [
                'total' => 10000,
            ],
        ];

        $payment = $hosted->createPaymentFromCallback($purchase);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status_id);
    }

    /**
     * When the chip payload's status is 'pending_charge', the Payment
     * record must be created with STATUS_PENDING. This is the
     * reviewer-flagged case: a 2xx response that is NOT yet settled
     * must not be marked completed.
     */
    public function testCreatePaymentFromCallbackMarksPendingChargeAsPending(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_pending_001',
            'status' => 'pending_charge',
            'purchase' => [
                'total' => 10000,
            ],
        ];

        $payment = $hosted->createPaymentFromCallback($purchase);

        $this->assertSame(Payment::STATUS_PENDING, $payment->status_id);
    }

    /**
     * When the chip payload's status is missing, throw PaymentFailed.
     * A successful 2xx without a status is a malformed payload.
     */
    public function testCreatePaymentFromCallbackRejectsMissingStatus(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_no_status',
            // no 'status' key
            'purchase' => [
                'total' => 10000,
            ],
        ];

        $this->expectException(PaymentFailed::class);

        $hosted->createPaymentFromCallback($purchase);
    }

    /**
     * When the chip payload's status is an unexpected value (not
     * 'paid' or 'pending_charge'), throw PaymentFailed. This catches
     * e.g. 'error', 'cancelled', 'expired' — the acquirer / chip
     * told us the charge did not succeed, so we must not record a
     * Payment at all (the webhook will be the authoritative signal
     * if the situation changes).
     */
    public function testCreatePaymentFromCallbackRejectsUnexpectedStatus(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_error_001',
            'status' => 'error',
            'purchase' => [
                'total' => 10000,
            ],
        ];

        $this->expectException(PaymentFailed::class);

        $hosted->createPaymentFromCallback($purchase);
    }

    /**
     * When a webhook arrives with status='paid' and an existing
     * pending payment exists for the same purchase, the payment
     * must transition from STATUS_PENDING to STATUS_COMPLETED.
     * This is the canonical pending->completed transition that
     * /charge/'s 'pending_charge' response relies on.
     */
    public function testTransitionPaymentStatusMovesPendingToCompletedOnPaid(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_transition_paid',
            'status' => 'pending_charge',
            'purchase' => ['total' => 10000],
        ];
        $pending = $hosted->createPaymentFromCallback($purchase);
        $this->assertSame(Payment::STATUS_PENDING, $pending->status_id);

        // Simulate a later 'paid' webhook arriving for the same purchase.
        $transitioned = $hosted->transitionPaymentStatus(
            $pending,
            'paid'
        );

        $this->assertTrue($transitioned);
        $this->assertSame(Payment::STATUS_COMPLETED, $pending->fresh()->status_id);
    }

    /**
     * When a webhook arrives with status='error' (or any other
     * failure-like status) and an existing pending payment exists,
     * the payment must transition from STATUS_PENDING to
     * STATUS_FAILED. This is the pending->failed transition.
     */
    public function testTransitionPaymentStatusMovesPendingToFailedOnError(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_transition_failed',
            'status' => 'pending_charge',
            'purchase' => ['total' => 10000],
        ];
        $pending = $hosted->createPaymentFromCallback($purchase);

        $transitioned = $hosted->transitionPaymentStatus(
            $pending,
            'error'
        );

        $this->assertTrue($transitioned);
        $this->assertSame(Payment::STATUS_FAILED, $pending->fresh()->status_id);
    }

    /**
     * When the chip status is still 'pending_charge' on a later
     * webhook, the existing pending payment must stay pending —
     * no transition.
     */
    public function testTransitionPaymentStatusLeavesPendingAloneOnPendingCharge(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_still_pending',
            'status' => 'pending_charge',
            'purchase' => ['total' => 10000],
        ];
        $pending = $hosted->createPaymentFromCallback($purchase);

        $transitioned = $hosted->transitionPaymentStatus(
            $pending,
            'pending_charge'
        );

        $this->assertFalse($transitioned);
        $this->assertSame(Payment::STATUS_PENDING, $pending->fresh()->status_id);
    }

    /**
     * When a completed payment is hit with another 'paid' webhook,
     * no transition is needed — the payment is already completed.
     */
    public function testTransitionPaymentStatusLeavesCompletedAloneOnPaid(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_already_completed',
            'status' => 'paid',
            'purchase' => ['total' => 10000],
        ];
        $completed = $hosted->createPaymentFromCallback($purchase);
        $this->assertSame(Payment::STATUS_COMPLETED, $completed->status_id);

        $transitioned = $hosted->transitionPaymentStatus(
            $completed,
            'paid'
        );

        $this->assertFalse($transitioned);
        $this->assertSame(Payment::STATUS_COMPLETED, $completed->fresh()->status_id);
    }

    private function buildHosted(float $amountWithFee): Hosted
    {
        $gateway = Gateway::where('provider', 'ChipInAsia')->firstOrFail();

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = $gateway->key;
        $cg->setConfig(['apiKey' => 'test', 'brandId' => 'test']);
        $cg->save();

        $hash = PaymentHash::create([
            'company_id' => $this->company->id,
            'hash' => str()->random(32),
            'data' => json_decode(json_encode([
                'amount_with_fee' => $amountWithFee,
                'invoices' => [],
            ])),
        ]);

        $driver = new ChipInAsiaPaymentDriver($cg, $this->client, null);
        $driver->setPaymentHash($hash);
        $driver->setPaymentMethod(GatewayType::HOSTED_PAGE);

        return new Hosted($driver);
    }
}
