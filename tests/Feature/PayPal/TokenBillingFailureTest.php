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
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Proves the gatewayRequest() >422 return-type fix.
 *
 * When PayPal returns an HTTP status greater than 422 (5xx / 429),
 * gatewayRequest() must return an Illuminate\Http\Client\Response so that
 * downstream ->json() calls resolve. With the pre-fix code it returned an
 * Illuminate\Http\JsonResponse, and tokenBilling()/createOrder() then called
 * ->json() on it. Because JsonResponse is Macroable, that raised a
 * BadMethodCallException ("Method ...JsonResponse::json does not exist")
 * instead of the real gateway decline reason — surfacing to a client-present
 * card-on-file payment as a raw JSON error, and to autobill as a misleading
 * failure message. Post-fix, the failure resolves to a PaymentFailed carrying
 * the actual reason.
 */
class TokenBillingFailureTest extends TestCase
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
        $config->merchantId = 'KDCGGYWFNWTAN';
        $config->status = 'activated';
        $config->clientId = config('ninja.paypal.client_id') ?: 'test-client-id';
        $config->secret = config('ninja.paypal.secret') ?: 'test-secret';

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = '80af24a6a691230bbec33e930ab40666'; // PayPal PPCP
        $cg->require_shipping_address = false;
        $cg->config = encrypt(json_encode($config));
        $cg->save();

        $fees_and_limits = new stdClass();
        $fees_and_limits->{3} = new FeesAndLimits();
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        return $cg;
    }

    private function buildToken(CompanyGateway $cg): ClientGatewayToken
    {
        $cgt = new ClientGatewayToken();
        $cgt->company_id = $this->company->id;
        $cgt->client_id = $this->client->id;
        $cgt->company_gateway_id = $cg->id;
        $cgt->gateway_type_id = GatewayType::PAYPAL;
        $cgt->token = 'vault-token-123';
        $cgt->gateway_customer_reference = 'cust-123';
        $cgt->save();

        return $cgt;
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

    /**
     * Fakes the OAuth token call plus a PayPal 5xx on order creation.
     */
    private function fakePayPal(int $order_status, array $order_body = ['name' => 'INTERNAL_SERVER_ERROR']): void
    {
        // Trailing wildcards are required — PayPal appends query strings
        // (e.g. ?grant_type=client_credentials), which a non-wildcard pattern
        // would fail to match, silently returning an empty fake response.
        Http::fake([
            '*/v1/oauth2/token*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
            '*/v2/checkout/orders*' => Http::response($order_body, $order_status),
        ]);
    }

    public function testTokenBillingThrowsCatchablePaymentFailedOn500(): void
    {
        $cg = $this->buildGateway();
        $cgt = $this->buildToken($cg);
        $payment_hash = $this->buildPaymentHash();

        $this->fakePayPal(500);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash);

        // Pre-fix: gatewayRequest returned a JsonResponse, and createOrder's
        // ->json() on it raised BadMethodCallException ("...JsonResponse::json
        // does not exist"). Post-fix: it resolves to a catchable PaymentFailed.
        $this->expectException(PaymentFailed::class);

        $driver->tokenBilling($cgt, $payment_hash);
    }

    public function testEmptyBodyGatewayFailureThrowsCatchablePaymentFailed(): void
    {
        $cg = $this->buildGateway();
        $cgt = $this->buildToken($cg);
        $payment_hash = $this->buildPaymentHash();

        // A bodyless 5xx — e.g. a PayPal edge/proxy 502/503 during an outage.
        // $r->json() decodes to null; without the ['name' => ''] guard,
        // handleProcessingFailure(array $response) raises an uncatchable
        // TypeError that escapes AutoBillInvoice's catch (\Exception).
        Http::fake([
            '*/v1/oauth2/token*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
            '*/v2/checkout/orders*' => Http::response('', 503),
        ]);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash);

        $this->expectException(PaymentFailed::class);

        $driver->tokenBilling($cgt, $payment_hash);
    }

    public function testAutoBillPipelineCatchesTheFailureAsException(): void
    {
        $cg = $this->buildGateway();
        $cgt = $this->buildToken($cg);
        $payment_hash = $this->buildPaymentHash();

        $this->fakePayPal(503);

        $driver = $cg->driver($this->client)->setPaymentHash($payment_hash);

        // Pre-fix this threw a BadMethodCallException ("...JsonResponse::json
        // does not exist") — a caught-but-misleading failure. Post-fix it must
        // be a PaymentFailed carrying the real gateway reason.
        try {
            $driver->tokenBilling($cgt, $payment_hash);
            $this->fail('Expected the gateway failure to raise an exception.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(PaymentFailed::class, $e);
            $this->assertStringNotContainsStringIgnoringCase('does not exist', $e->getMessage());
        }
    }
}
