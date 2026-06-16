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
use App\Models\PaymentHash;
use App\PaymentDrivers\ChipInAsia\Hosted;
use App\PaymentDrivers\ChipInAsiaPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Tests for the anti-tampering check on the amount in
 * Hosted::createPaymentFromCallback.
 *
 * Per reviewer feedback, the Payment record's amount must come from
 * the trusted payment_hash->amount_with_fee(), and the chip payload's
 * purchase.total must match. If they differ, the payload is
 * suspicious (modified in flight, or a different purchase than the
 * one we initiated) and must be rejected.
 */
class CreatePaymentAntiTamperingTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    /**
     * A chip payload reporting a smaller total than expected must be
     * rejected. This is the canonical tamper case: the customer paid
     * the full amount but the payload (or a man-in-the-middle) is
     * trying to make us record a smaller payment.
     */
    public function testCreatePaymentFromCallbackRejectsUnderreportedTotal(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_tamper_under',
            'status' => 'paid',
            'purchase' => [
                'total' => 1, // 1 cent — but amount_with_fee is 100.00
            ],
        ];

        $this->expectException(PaymentFailed::class);

        $hosted->createPaymentFromCallback($purchase);
    }

    /**
     * A chip payload reporting a larger total than expected must also
     * be rejected. The Payment record's amount is the source of truth
     * for accounting, and it must match the merchant's intent.
     */
    public function testCreatePaymentFromCallbackRejectsOverreportedTotal(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_tamper_over',
            'status' => 'paid',
            'purchase' => [
                'total' => 20000, // 200.00 — but amount_with_fee is 100.00
            ],
        ];

        $this->expectException(PaymentFailed::class);

        $hosted->createPaymentFromCallback($purchase);
    }

    /**
     * A chip payload with a matching total must be accepted. This is
     * the happy path and must keep working after the anti-tampering
     * check is in place.
     */
    public function testCreatePaymentFromCallbackAcceptsMatchingTotal(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_happy_path',
            'status' => 'paid',
            'purchase' => [
                'total' => 10000, // 100.00 in cents — matches amount_with_fee
            ],
        ];

        $payment = $hosted->createPaymentFromCallback($purchase);

        // payment.amount comes from payment_hash->amount_with_fee (a float).
        $this->assertEquals(100.00, $payment->amount);
    }

    /**
     * A chip payload that omits the total must be rejected. The total
     * is part of the verified purchase record — if it's missing, we
     * have no way to confirm the right amount was charged.
     */
    public function testCreatePaymentFromCallbackRejectsMissingTotal(): void
    {
        $hosted = $this->buildHosted(amountWithFee: 100.00);

        $purchase = [
            'id' => 'purchase_tamper_missing',
            'status' => 'paid',
            'purchase' => [
                // no 'total' key
            ],
        ];

        $this->expectException(PaymentFailed::class);

        $hosted->createPaymentFromCallback($purchase);
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
