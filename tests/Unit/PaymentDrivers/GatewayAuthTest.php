<?php

namespace Tests\Unit\PaymentDrivers;

use App\Models\CompanyGateway;
use App\PaymentDrivers\BTCPayPaymentDriver;
use App\PaymentDrivers\BraintreePaymentDriver;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use App\PaymentDrivers\ChipInAsiaPaymentDriver;
use App\PaymentDrivers\FortePaymentDriver;
use App\PaymentDrivers\HelcimPaymentDriver;
use App\PaymentDrivers\PayFastPaymentDriver;
use App\PaymentDrivers\PaytracePaymentDriver;
use App\PaymentDrivers\RazorpayPaymentDriver;
use App\PaymentDrivers\RotessaPaymentDriver;
use App\PaymentDrivers\SquarePaymentDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayAuthTest extends TestCase
{
    public function testPayFastAuthUsesSignedPingRequest(): void
    {
        $gateway = $this->companyGateway([
            'merchantId' => '10000100',
            'merchantKey' => 'merchant-key',
            'passphrase' => 'secret-passphrase',
            'testMode' => true,
        ]);

        Http::fake(function (Request $request) {
            $signatureData = [
                'merchant-id' => $request->header('merchant-id')[0],
                'version' => $request->header('version')[0],
                'timestamp' => $request->header('timestamp')[0],
            ];

            $this->assertSame(
                \PayFast\Auth::generateApiSignature($signatureData, 'secret-passphrase'),
                $request->header('signature')[0]
            );
            $this->assertStringContainsString('testing=true', $request->url());

            return Http::response('API V1', 200);
        });

        $this->assertSame('ok', (new PayFastPaymentDriver($gateway))->auth());
        Http::assertSentCount(1);
    }

    public function testCheckoutAuthValidatesTheFullFlowConfiguration(): void
    {
        $secretKey = 'sk_sbox_' . str_repeat('a', 27);
        $publicKey = 'pk_sbox_' . str_repeat('b', 27);
        $gateway = $this->companyGateway([
            'secretApiKey' => $secretKey,
            'publicApiKey' => $publicKey,
            'processingChannelId' => 'pc_test',
            'testMode' => true,
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/payments/pay_')) {
                return Http::response([], 404);
            }

            if (str_contains($request->url(), '/payment-methods')) {
                return Http::response(['methods' => []], 200);
            }

            if (str_contains($request->url(), '/tokens')) {
                return Http::response(['error_codes' => ['request_invalid']], 422);
            }

            return Http::response([], 500);
        });

        $this->assertSame('ok', (new CheckoutComPaymentDriver($gateway))->auth());

        Http::assertSent(function (Request $request) use ($secretKey) {
            return str_contains($request->url(), '/payments/pay_')
                && $request->hasHeader('Authorization', 'Bearer ' . $secretKey);
        });
        Http::assertSent(function (Request $request) use ($secretKey) {
            return str_contains($request->url(), '/payment-methods')
                && str_contains($request->url(), 'processing_channel_id=pc_test')
                && $request->hasHeader('Authorization', 'Bearer ' . $secretKey);
        });
        Http::assertSent(function (Request $request) use ($publicKey) {
            return str_contains($request->url(), '/tokens')
                && $request->hasHeader('Authorization', 'Bearer ' . $publicKey)
                && $request['type'] === 'card';
        });
        Http::assertSentCount(3);
    }

    public function testCheckoutAuthRejectsAnInvalidPublicKeyResponse(): void
    {
        $gateway = $this->companyGateway([
            'secretApiKey' => 'sk_sbox_' . str_repeat('a', 27),
            'publicApiKey' => 'pk_sbox_' . str_repeat('b', 27),
            'processingChannelId' => '',
            'testMode' => true,
        ]);

        Http::fake(function (Request $request) {
            return str_contains($request->url(), '/payments/pay_')
                ? Http::response([], 404)
                : Http::response([], 401);
        });

        $this->assertSame('error', (new CheckoutComPaymentDriver($gateway))->auth());
        Http::assertSentCount(2);
    }

    public function testPaytraceAuthExercisesTheIntegratorId(): void
    {
        $gateway = $this->companyGateway([
            'username' => 'user',
            'password' => 'password',
            'integratorId' => '123456789012',
        ]);

        $driver = new class ($gateway) extends PaytracePaymentDriver {
            public string $uri = '';

            public array $payload = [];

            public function gatewayRequest($uri, $data, $headers = false)
            {
                $this->uri = $uri;
                $this->payload = $data;

                return (object) ['success' => true];
            }
        };

        $this->assertSame('ok', $driver->auth());
        $this->assertSame('/v1/customer/export', $driver->uri);
        $this->assertSame('123456789012', $driver->payload['integrator_id']);
        $this->assertSame('invoice_ninja_auth_check', $driver->payload['customer_id']);
    }

    public function testBraintreeRejectsMissingRequiredCredentials(): void
    {
        $missingMerchant = $this->companyGateway([
            'merchantId' => '',
            'merchantAccountId' => '',
            'publicKey' => 'public',
            'privateKey' => 'private',
        ]);
        $missingPublicKey = $this->companyGateway([
            'merchantId' => 'merchant',
            'merchantAccountId' => '',
            'publicKey' => '',
            'privateKey' => 'private',
        ]);

        $this->assertSame('error', (new BraintreePaymentDriver($missingMerchant))->auth());
        $this->assertSame('error', (new BraintreePaymentDriver($missingPublicKey))->auth());
    }

    public function testSquareLocationIdIsRequiredByThisWebPaymentsIntegration(): void
    {
        $gateway = $this->companyGateway([
            'accessToken' => 'token',
            'applicationId' => 'application',
            'locationId' => '',
            'oauth2' => false,
        ]);

        $this->assertSame('error', (new SquarePaymentDriver($gateway))->auth());
    }

    public function testRazorpayRequiresBothApiCredentials(): void
    {
        $gateway = $this->companyGateway(['apiKey' => 'key', 'apiSecret' => '']);

        $this->assertSame('error', (new RazorpayPaymentDriver($gateway))->auth());
    }

    public function testForteUsesTheAuthorizationOrganizationHeader(): void
    {
        $gateway = $this->companyGateway([
            'apiAccessId' => 'access',
            'secureKey' => 'secret',
            'authOrganizationId' => 'org_auth',
            'organizationId' => 'org_target',
            'locationId' => 'loc_target',
            'testMode' => true,
        ]);

        Http::fake(['*' => Http::response(['location_id' => 'loc_target'], 200)]);

        $this->assertSame('ok', (new FortePaymentDriver($gateway))->auth());
        Http::assertSent(function (Request $request) {
            return $request->hasHeader('X-Forte-Auth-Organization-Id', 'org_auth')
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('access:secret'))
                && str_contains($request->url(), '/organizations/org_target/locations/loc_target');
        });
    }

    public function testBtcpayAuthValidatesTheConfiguredStore(): void
    {
        $gateway = $this->companyGateway([
            'btcpayUrl' => 'https://btcpay.example.test/',
            'apiKey' => 'api-key',
            'storeId' => 'store-id',
            'webhookSecret' => 'webhook-secret',
        ]);

        Http::fake(['*' => Http::response(['id' => 'store-id'], 200)]);

        $this->assertSame('ok', (new BTCPayPaymentDriver($gateway))->auth());
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://btcpay.example.test/api/v1/stores/store-id'
                && $request->hasHeader('Authorization', 'token api-key');
        });
    }

    public function testRotessaUsesItsDocumentedTokenAuthorizationScheme(): void
    {
        $gateway = $this->companyGateway(['apiKey' => 'rotessa-key', 'testMode' => true]);
        Http::fake(['*' => Http::response([], 404)]);

        $this->assertSame('ok', (new RotessaPaymentDriver($gateway))->auth());
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://sandbox-api.rotessa.com/v1/customers/0'
                && $request->hasHeader('Authorization', 'Token token="rotessa-key"');
        });
    }

    public function testChipAuthValidatesTheBrandAndApiKeyTogether(): void
    {
        $gateway = $this->companyGateway(['apiKey' => 'chip-key', 'brandId' => 'brand-id']);
        Http::fake(['*' => Http::response(['available_payment_methods' => ['visa']], 200)]);

        $this->assertSame('ok', (new ChipInAsiaPaymentDriver($gateway))->auth());
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/payment_methods/')
                && str_contains($request->url(), 'brand_id=brand-id')
                && str_contains($request->url(), 'currency=MYR')
                && $request->hasHeader('Authorization', 'Bearer chip-key');
        });
    }

    public function testHelcimDoesNotAcceptArbitraryNonAuthenticationErrors(): void
    {
        $gateway = $this->companyGateway(['apiToken' => 'helcim-token']);
        Http::fake(['*' => Http::response(['message' => 'server failure'], 500)]);

        $this->assertNotSame('ok', (new HelcimPaymentDriver($gateway))->auth());
    }

    public function testHelcimAuthUsesSupportedCustomerSearchParameters(): void
    {
        $gateway = $this->companyGateway(['apiToken' => 'helcim-token']);
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->assertSame('ok', (new HelcimPaymentDriver($gateway))->auth());
        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/customers')
                && $query === ['search' => 'ping', 'limit' => '1']
                && $request->hasHeader('api-token', 'helcim-token');
        });
    }

    private function companyGateway(array $config): CompanyGateway
    {
        $gateway = new CompanyGateway();
        $gateway->setConfig($config);

        return $gateway;
    }
}
