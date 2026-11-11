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

namespace Tests\Unit\PaymentDrivers\Revolut;

use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\RevolutPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Revolut Pay: saved accounts and merchant-initiated charges. Revolut is
 * never called: Http is faked.
 */
class RevolutPayTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private RevolutPaymentDriver $driver;

    private CompanyGateway $company_gateway;

    private PaymentHash $payment_hash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->buildDriver(['publicKey' => 'pk_test']);
    }

    public function testRevolutPayIsItsOwnGatewayAndPaymentType(): void
    {
        $this->assertSame('Revolut Pay', GatewayType::getAlias(GatewayType::REVOLUT_PAY));
        $this->assertSame('Revolut Pay', (new PaymentType())->name(PaymentType::REVOLUT_PAY));
        $this->assertContains(GatewayType::REVOLUT_PAY, $this->driver->gatewayTypes());
    }

    public function testTokenBillingChargesTheSavedRevolutPayAccount(): void
    {
        $cgt = $this->savedAccount();

        Http::fake([
            '*/api/orders/mit_order/payments' => Http::response(['id' => 'payment_1', 'state' => 'authorisation_passed']),
            '*/api/orders/mit_order' => Http::response($this->order('completed')),
            '*/api/orders' => Http::response($this->order('pending')),
        ]);

        $payment = $this->driver->tokenBilling($cgt, $this->payment_hash);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status_id);
        $this->assertSame(PaymentType::REVOLUT_PAY, (int) $payment->type_id);
        $this->assertSame(GatewayType::REVOLUT_PAY, (int) $payment->gateway_type_id);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/orders/mit_order/payments')
            && $request['saved_payment_method'] === ['type' => 'revolut_pay', 'id' => 'rp_1', 'initiator' => 'merchant']);
    }

    public function testPayingWithASavedRevolutPayAccountInThePortalIsMerchantInitiated(): void
    {
        $cgt = $this->savedAccount();
        $this->driver->setPaymentMethod(GatewayType::REVOLUT_PAY);

        Http::fake([
            '*/api/orders/mit_order/payments' => Http::response(['id' => 'payment_1', 'state' => 'pending']),
            '*/api/orders/mit_order' => Http::response($this->order('completed')),
            '*/api/orders' => Http::response($this->order('pending')),
        ]);

        $request = \App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest::create('/payments/process/response', 'POST', ['token' => $cgt->hashed_id]);

        $response = $this->driver->payment_method->paymentResponse($request);

        $this->assertStringContainsString('/client/payments/', $response->getTargetUrl());
        $this->assertSame(PaymentType::REVOLUT_PAY, (int) Payment::where('transaction_reference', 'mit_order')->value('type_id'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/orders/mit_order/payments')
            && $request['saved_payment_method'] === ['type' => 'revolut_pay', 'id' => 'rp_1', 'initiator' => 'merchant']);
    }

    public function testTheRevolutPayAccountIsSavedWhenThePaymentIsRecordedWithNoCardDetails(): void
    {
        $this->payment_hash->withData('gateway_type_id', GatewayType::REVOLUT_PAY);

        Http::fake([
            '*/api/customers/cus_1/payment-methods' => Http::response(['payment_methods' => [['id' => 'rp_new', 'type' => 'revolut_pay', 'saved_for' => 'merchant']]]),
        ]);

        // As the webhook does, possibly before the customer is back in the portal.
        $this->driver->recordOrder($this->order('completed', 'rp_new'));

        $token = ClientGatewayToken::where('token', 'rp_new')->firstOrFail();
        $this->assertSame(GatewayType::REVOLUT_PAY, (int) $token->gateway_type_id);
        $this->assertSame('Revolut Pay', $token->meta->brand);
        $this->assertObjectNotHasProperty('last4', $token->meta);
    }

    public function testRevolutPayNeedsThePublicKey(): void
    {
        $this->buildDriver([]);
        $this->driver->setPaymentMethod(GatewayType::REVOLUT_PAY);

        Http::fake();

        $this->expectException(PaymentFailed::class);

        $this->driver->payment_method->paymentData([]);
    }

    private function savedAccount(): ClientGatewayToken
    {
        $cgt = new ClientGatewayToken();
        $cgt->company_id = $this->company->id;
        $cgt->client_id = $this->client->id;
        $cgt->company_gateway_id = $this->company_gateway->id;
        $cgt->gateway_type_id = GatewayType::REVOLUT_PAY;
        $cgt->token = 'rp_1';
        $cgt->gateway_customer_reference = 'cus_1';
        $cgt->meta = (object) ['brand' => 'Revolut Pay', 'type' => GatewayType::REVOLUT_PAY];
        $cgt->save();

        return $cgt;
    }

    private function order(string $state, string $method_id = 'rp_1'): array
    {
        return [
            'id' => 'mit_order',
            'token' => 'public_mit_order',
            'state' => $state,
            'amount' => 5000,
            'currency' => $this->client->getCurrencyCode(),
            'customer' => ['id' => 'cus_1'],
            'payments' => $state === 'completed' ? [[
                'state' => 'captured',
                'payment_method' => ['id' => $method_id, 'type' => 'revolut_pay'],
            ]] : [],
        ];
    }

    private function buildDriver(array $config): void
    {
        $gateway = Gateway::where('provider', 'Revolut')->firstOrFail();

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = $gateway->key;
        $this->company_gateway->token_billing = 'optout';
        $this->company_gateway->setConfig(array_merge(['apiKey' => 'sk_test', 'signingSecret' => 'wsk_test', 'testMode' => true], $config));
        $this->company_gateway->save();

        $this->payment_hash = PaymentHash::create([
            'company_id' => $this->company->id,
            'hash' => str()->random(32),
            'fee_invoice_id' => $this->invoice->id,
            'data' => json_decode(json_encode([
                'amount_with_fee' => 50.00,
                'invoices' => [[
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 50.00,
                    'invoice_number' => $this->invoice->number,
                ]],
            ])),
        ]);

        $this->driver = new RevolutPaymentDriver($this->company_gateway, $this->client, null);
        $this->driver->setPaymentHash($this->payment_hash);
    }
}
