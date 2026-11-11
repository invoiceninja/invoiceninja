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

namespace Tests\Unit\PaymentDrivers\Stripe;

use App\Models\Payment;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Regression tests for the dual-key Stripe webhook lookup helper.
 *
 * Several Stripe drivers (ACH, ACSS, BankTransfer, SEPA, BACS, BECS, iDEAL,
 * SOFORT, Bancontact, GIROPAY, EPS, Przelewy24, Klarna, FPX, Alipay) write
 * the PaymentIntent ID (pi_*) into Payment.transaction_reference rather than
 * the Charge ID (ch_ / py_). For async-settling bank methods this is
 * unavoidable since no Charge exists yet at write time. Webhook events like
 * charge.refunded carry the Charge ID, so a single-key lookup misses these
 * payments. The helper must match on either reference.
 */
class FindPaymentByStripeReferenceTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testMatchesByChargeIdWhenStoredAsChargeId(): void
    {
        $payment = $this->makePayment('ch_test_charge_001');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'ch_test_charge_001',
            'payment_intent' => 'pi_test_intent_001',
        ]);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function testMatchesByPaymentIntentWhenStoredAsPaymentIntent(): void
    {
        // This is the actual bug: ACH/ACSS/etc. store pi_ as transaction_reference,
        // but charge.refunded webhook arrives with id=ch_/py_.
        $payment = $this->makePayment('pi_test_intent_002');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'py_test_charge_002',
            'payment_intent' => 'pi_test_intent_002',
        ]);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function testReturnsNullWhenNeitherReferenceMatches(): void
    {
        $this->makePayment('ch_some_other_charge');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'ch_does_not_exist',
            'payment_intent' => 'pi_does_not_exist',
        ]);

        $this->assertNull($found);
    }

    public function testReturnsNullWhenSourceHasNoUsableIds(): void
    {
        $this->makePayment('ch_anything');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, []);

        $this->assertNull($found);
    }

    public function testTreatsIdAsPaymentIntentWhenItStartsWithPi(): void
    {
        // Some webhook payloads (e.g. payment_intent.* events) put pi_ in `id`
        // and have no `payment_intent` field. The helper should still find the
        // payment that was stored under that pi_.
        $payment = $this->makePayment('pi_test_intent_003');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'pi_test_intent_003',
        ]);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function testScopesToCompany(): void
    {
        // Same transaction_reference under a different company must not match.
        $this->makePayment('ch_shared_reference');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id + 99999, [
            'id' => 'ch_shared_reference',
        ]);

        $this->assertNull($found);
    }

    public function testDoesNotFindTrashedPaymentByDefault(): void
    {
        $payment = $this->makePayment('ch_trashed_001');
        $payment->delete();

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'ch_trashed_001',
        ]);

        $this->assertNull($found);
    }

    public function testFindsTrashedPaymentWhenWithTrashedRequested(): void
    {
        // ChargeRefunded passes withTrashed=true so soft-deleted (archived)
        // payments can still be reconciled when their refund webhook lands.
        $payment = $this->makePayment('ch_trashed_002');
        $payment->delete();

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'ch_trashed_002',
        ], withTrashed: true);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function testMatchesViaLatestChargeOnPaymentIntentPayload(): void
    {
        // payment_intent.* webhooks carry no top-level `payment_intent` field,
        // but expose the charge id via `latest_charge`. If the payment was
        // recorded against the charge id, the helper must still find it.
        $payment = $this->makePayment('ch_latest_charge_001');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'pi_intent_lc_001',
            'latest_charge' => 'ch_latest_charge_001',
        ]);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function testMatchesViaNestedChargesDataOnPaymentIntentPayload(): void
    {
        // Older API versions expose the charge id under `charges.data[0].id`.
        $payment = $this->makePayment('ch_nested_001');

        $found = StripePaymentDriver::findPaymentByStripeReference($this->company->id, [
            'id' => 'pi_intent_nested_001',
            'charges' => ['data' => [['id' => 'ch_nested_001']]],
        ]);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    private function makePayment(string $transactionReference): Payment
    {
        return Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'transaction_reference' => $transactionReference,
        ]);
    }
}
