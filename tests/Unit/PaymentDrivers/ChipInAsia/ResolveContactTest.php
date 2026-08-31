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
use App\Models\ClientContact;
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
 * Tests for the resolveContact() helper extracted from the call sites
 * of Hosted::paymentData and Hosted::createPurchaseForTokenCharge.
 *
 * The helper centralises contact resolution so the payload builders
 * can treat the contact as a real ClientContact with a guaranteed
 * email, rather than leaning on null-coalescing fallbacks that mask
 * the missing-contact case.
 */
class ResolveContactTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    /**
     * When no invitation is set, no auth guard is set, and the client
     * has no contacts with an email, paymentData must throw
     * PaymentFailed with an email-related message rather than
     * silently submitting a payload to CHIP that will be rejected.
     */
    public function testPaymentDataThrowsWhenNoContactIsResolvable(): void
    {
        $this->client->contacts()->delete();
        $this->client->phone = null;
        $this->client->save();

        $hosted = $this->buildHosted();

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessageMatches('/email/i');

        $hosted->paymentData([
            'payment_hash' => $this->paymentHash->hash,
            'company_gateway_id' => $this->companyGateway->id,
            'payment_method_id' => GatewayType::HOSTED_PAGE,
        ]);
    }

    /**
     * When getContact() yields no contact, the helper must NOT silently
     * fall back to a different contact on the same client — even if
     * that other contact has an email. Each payment is tied to a
     * specific contact via the InvoiceInvitation; routing the email to
     * a different person is a privacy/correctness bug.
     *
     * The right behavior is to throw PaymentFailed so the merchant
     * fixes the actual payer's contact record.
     */
    public function testPaymentDataThrowsEvenWhenAnotherClientContactHasAnEmail(): void
    {
        // Clear the default contact that MockAccountData creates.
        $this->client->contacts()->delete();

        // Create a contact with an email — but it's NOT the one
        // getContact() resolves to, since the driver has no invitation
        // and no auth guard. The helper must refuse to use this.
        ClientContact::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'email' => 'fallback@gmail.com',
            'first_name' => 'Fallback',
            'last_name' => 'Contact',
        ]);

        $hosted = $this->buildHosted();

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessageMatches('/email/i');

        $hosted->paymentData([
            'payment_hash' => $this->paymentHash->hash,
            'company_gateway_id' => $this->companyGateway->id,
            'payment_method_id' => GatewayType::HOSTED_PAGE,
        ]);
    }

    private CompanyGateway $companyGateway;
    private PaymentHash $paymentHash;

    private function buildHosted(): Hosted
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
                'amount_with_fee' => 100.0,
                'invoices' => [],          // paymentData() / getDescription() walks this
            ])),
        ]);

        $driver = new ChipInAsiaPaymentDriver($cg, $this->client, null);
        $driver->setPaymentHash($hash);
        $driver->setPaymentMethod(GatewayType::HOSTED_PAGE);

        $this->companyGateway = $cg;
        $this->paymentHash = $hash;

        return new Hosted($driver);
    }
}
