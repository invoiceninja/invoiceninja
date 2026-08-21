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

namespace Tests\Feature\PayPal;

use stdClass;
use Tests\TestCase;
use Tests\MockAccountData;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use Illuminate\Support\Str;
use App\Models\CompanyGateway;
use App\DataMapper\FeesAndLimits;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PayPalRestDriverTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function buildGateway(): CompanyGateway
    {
        $config = new stdClass();
        $config->clientId = 'test-client-id';
        $config->secret = 'test-secret';
        $config->testMode = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = '80af24a6a691230bbec33e930ab40665';
        $cg->require_shipping_address = false;
        $cg->config = encrypt(json_encode($config));
        $cg->save();

        $fees_and_limits = new stdClass();
        $fees_and_limits->{3} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        return $cg;
    }

    private function buildPaymentHash(): PaymentHash
    {
        $payment_hash = new PaymentHash();
        $payment_hash->hash = Str::random(32);
        $payment_hash->data = [
            'amount_with_fee' => 100,
            'invoices' => [
                ['invoice_id' => $this->invoice->hashed_id, 'amount' => 100, 'invoice_number' => $this->invoice->number],
            ],
        ];
        $payment_hash->fee_total = 0;
        $payment_hash->fee_invoice_id = $this->invoice->id;
        $payment_hash->save();

        return $payment_hash;
    }

    public function testCreateOrderFormatsAmountToCurrencyPrecision(): void
    {
        $cg = $this->buildGateway();
        $payment_hash = $this->buildPaymentHash();
        $payment_hash->data = array_merge((array) $payment_hash->data, ['amount_with_fee' => 10.000000]);
        $payment_hash->save();

        Http::fake([
            '*/v1/oauth2/token*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
            '*/v2/checkout/orders*' => function ($request) {
                $payload = json_decode($request->body(), true);
                $this->assertSame('10.00', $payload['purchase_units'][0]['amount']['value']);

                return Http::response(['id' => 'ORDER123', 'status' => 'CREATED'], 201);
            },
        ]);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash)->setPaymentMethod(GatewayType::PAYPAL);
        $order_id = $driver->createOrder(['amount_with_fee' => 10.000000]);

        $this->assertSame('ORDER123', $order_id);
    }

    public function testCreateOrderUsesPaymentHashSuffixInInvoiceId(): void
    {
        $cg = $this->buildGateway();
        $payment_hash = $this->buildPaymentHash();
        $expected_invoice_id = $this->invoice->number . '-' . substr($payment_hash->hash, 0, 8);

        Http::fake([
            '*/v1/oauth2/token*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
            '*/v1/identity/generate-token*' => Http::response(['client_token' => 'client-token'], 200),
            '*/v2/checkout/orders*' => function ($request) use ($expected_invoice_id, $payment_hash) {
                $payload = json_decode($request->body(), true);
                $this->assertSame($expected_invoice_id, $payload['purchase_units'][0]['invoice_id']);
                $this->assertSame($payment_hash->hash, $payload['purchase_units'][0]['custom_id']);

                return Http::response(['id' => 'ORDER123', 'status' => 'CREATED'], 201);
            },
        ]);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash)->setPaymentMethod(GatewayType::PAYPAL);
        $order_id = $driver->createOrder(['amount_with_fee' => 100]);

        $this->assertSame('ORDER123', $order_id);
    }

    public function testCreateOrderRetriesOnDuplicateInvoiceId(): void
    {
        $cg = $this->buildGateway();
        $payment_hash = $this->buildPaymentHash();
        $attempt = 0;

        Http::fake([
            '*/v1/oauth2/token*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
            '*/v2/checkout/orders*' => function () use (&$attempt) {
                $attempt++;

                if ($attempt === 1) {
                    return Http::response([
                        'name' => 'UNPROCESSABLE_ENTITY',
                        'details' => [['issue' => 'DUPLICATE_INVOICE_ID', 'description' => 'Duplicate']],
                    ], 422);
                }

                return Http::response(['id' => 'ORDER456', 'status' => 'CREATED'], 201);
            },
        ]);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash)->setPaymentMethod(GatewayType::PAYPAL);
        $order_id = $driver->createOrder(['amount_with_fee' => 100]);

        $this->assertSame(2, $attempt);
        $this->assertSame('ORDER456', $order_id);
    }

    public function testProcessPaymentResponseReturnsPayPalErrorForNonDuplicate422(): void
    {
        $cg = $this->buildGateway();
        $payment_hash = $this->buildPaymentHash();
        $payment_hash->data = array_merge((array) $payment_hash->data, ['orderID' => 'ORDER789']);
        $payment_hash->save();

        Http::fake([
            '*/v1/oauth2/token*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
            '*/v2/checkout/orders/ORDER789/capture*' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [['issue' => 'AMOUNT_MISMATCH', 'description' => 'Amount mismatch detected']],
            ], 422),
        ]);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash)->setPaymentMethod(GatewayType::PAYPAL);

        $request = request()->merge([
            'gateway_response' => json_encode(['orderID' => 'ORDER789']),
            'gateway_type_id' => GatewayType::PAYPAL,
            'payment_hash' => $payment_hash->hash,
        ]);

        $response = $driver->processPaymentResponse($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Amount mismatch detected', $response->getData(true)['message']);
    }

    public function testProcessPaymentResponseRescuesDuplicateInvoiceIdOnCapture(): void
    {
        $cg = $this->buildGateway();
        $payment_hash = $this->buildPaymentHash();
        $payment_hash->data = array_merge((array) $payment_hash->data, ['orderID' => 'ORDER999']);
        $payment_hash->save();

        Http::fake([
            '*/v1/oauth2/token*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
            '*/v2/checkout/orders/ORDER999' => Http::response(['id' => 'ORDER999', 'status' => 'APPROVED'], 200),
            '*/v2/checkout/orders/ORDER999/capture*' => Http::sequence()
                ->push([
                    'name' => 'UNPROCESSABLE_ENTITY',
                    'details' => [['issue' => 'DUPLICATE_INVOICE_ID', 'description' => 'Duplicate invoice id']],
                ], 422)
                ->push([
                    'id' => 'ORDER999',
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'payments' => [
                            'captures' => [[
                                'id' => 'CAPTURE123',
                                'status' => 'COMPLETED',
                                'amount' => ['value' => '100.00'],
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash)->setPaymentMethod(GatewayType::PAYPAL);

        $request = request()->merge([
            'gateway_response' => json_encode(['orderID' => 'ORDER999']),
            'gateway_type_id' => GatewayType::PAYPAL,
            'payment_hash' => $payment_hash->hash,
        ]);

        $response = $driver->processPaymentResponse($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('redirect', $response->getData(true));
    }

    public function testGatewayTypesSuppressesLegacyCardWhenAdvancedCardsEnabled(): void
    {
        $cg = $this->buildGateway();

        $fees_and_limits = new stdClass();
        $fees_and_limits->{1} = new FeesAndLimits();
        $fees_and_limits->{3} = new FeesAndLimits();
        $fees_and_limits->{29} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        $driver = $cg->driver($this->client);
        $types = $driver->gatewayTypes();

        $this->assertContains(GatewayType::PAYPAL, $types);
        $this->assertContains(GatewayType::PAYPAL_ADVANCED_CARDS, $types);
        $this->assertNotContains(GatewayType::CREDIT_CARD, $types);
    }
}
