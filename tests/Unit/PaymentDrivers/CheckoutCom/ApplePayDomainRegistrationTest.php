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

namespace Tests\Unit\PaymentDrivers\CheckoutCom;

use App\Models\Company;
use App\Models\CompanyGateway;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\DefaultHttpClientBuilder;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ApplePayDomainRegistrationTest extends TestCase
{
    private const SECRET_KEY = 'sk_aaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'applepay_test',
            'database.connections.applepay_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('applepay_test');
        Schema::create('company_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_key');
            $table->text('config');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('applepay_test');
        parent::tearDown();
    }

    private function gateway(array $overrides = []): CompanyGateway
    {
        $gateway = new CompanyGateway();
        $gateway->setRelation('company', new Company());
        $gateway->gateway_key = '3758e7f7c6f4cecf0f4f348b9a00f456';
        $gateway->setConfig((object) array_merge([
            'secretApiKey' => self::SECRET_KEY,
            'processingChannelId' => 'pc_test',
            'clientId' => 'cli_vkuhvk4vabcdefgh',
            'testMode' => true,
        ], $overrides));

        $gateway->id = DB::table('company_gateways')->insertGetId([
            'gateway_key' => $gateway->gateway_key,
            'config' => $gateway->config,
        ]);
        $gateway->exists = true;

        return $gateway;
    }

    private function driver(CompanyGateway $gateway, callable $handler): CheckoutComPaymentDriver
    {
        return new class ($gateway, $handler) extends CheckoutComPaymentDriver {
            public function __construct(CompanyGateway $gateway, private $handler)
            {
                parent::__construct($gateway);
            }

            protected function applePayHttpClient(): DefaultHttpClientBuilder
            {
                $http = parent::applePayHttpClient();
                $http->getClient()->getConfig('handler')->setHandler($this->handler);

                return $http;
            }
        };
    }

    public function testEnrollmentUsesExistingSecretKeyAndEndpointConfiguration(): void
    {
        foreach ([true, false] as $sandbox) {
            foreach (['cli_vkuhvk4vabcdefgh', ''] as $client_id) {
                $calls = [];
                $driver = $this->driver($this->gateway(['testMode' => $sandbox, 'clientId' => $client_id]), function ($request, $options) use (&$calls) {
                    $calls[] = [$request, $options];

                    return Create::promiseFor(new Response(204));
                });
                $driver->setApplePayDomain('billing.example.com');

                $this->assertCount(1, $calls);
                [$request, $options] = $calls[0];
                $prefix = $client_id === '' ? '' : 'vkuhvk4v.';
                $suffix = $sandbox ? '.sandbox.checkout.com' : '.checkout.com';
                $this->assertSame('https://' . $prefix . 'api' . $suffix . '/applepay/enrollments', (string) $request->getUri());
                $this->assertSame(['domain' => 'billing.example.com'], json_decode((string) $request->getBody(), true));
                $this->assertSame('Bearer ' . self::SECRET_KEY, $request->getHeaderLine('Authorization'));
                $this->assertTrue($options['verify']);
                $this->assertFalse($options['allow_redirects']);
            }
        }
    }

    public function testEnrollmentFailuresDoNotExposeProviderResponse(): void
    {
        foreach ([401, 403, 422, 429, 503] as $status) {
            $driver = $this->driver($this->gateway(), new MockHandler([new Response($status, [], 'do-not-log-this')]));
            try {
                $driver->setApplePayDomain('billing.example.com');
                $this->fail('Expected enrollment failure.');
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('do-not-log-this', $e->getMessage());
                $this->assertNull($e->getPrevious());
            }
        }
    }

    public function testUnexpectedSuccessCodeIsNotAccepted(): void
    {
        $driver = $this->driver($this->gateway(), new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{}')]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not confirm');
        $driver->setApplePayDomain('billing.example.com');
    }

    public function testEnrollmentFailureLogsProviderDiagnostics(): void
    {
        $gateway = $this->gateway();
        $driver = $this->driver($gateway, new MockHandler([new Response(403, ['Content-Type' => 'application/json'], json_encode([
            'request_id' => 'req_enrollment',
            'error_type' => 'authorization_error',
            'error_codes' => ['insufficient_scope'],
            'sensitive_field' => self::SECRET_KEY,
        ]))]));

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) use ($gateway) {
            $this->assertSame('Checkout.com Apple Pay enrollment request failed.', $message);
            $this->assertSame($gateway->id, $context['company_gateway_id']);
            $this->assertSame('billing.example.com', $context['domain']);
            $this->assertSame('sandbox', $context['environment']);
            $this->assertSame(403, $context['http_status']);
            $this->assertSame('req_enrollment', $context['request_id']);
            $this->assertSame('authorization_error', $context['error_type']);
            $this->assertSame(['insufficient_scope'], $context['error_codes']);
            $this->assertStringNotContainsString(self::SECRET_KEY, json_encode($context));

            return true;
        });

        $this->expectException(RuntimeException::class);
        $driver->setApplePayDomain('billing.example.com');
    }

    public function testEnrollmentRequiresFlow(): void
    {
        $driver = $this->driver($this->gateway(['processingChannelId' => '']), new MockHandler([]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configure Checkout.com Flow');
        $driver->setApplePayDomain('billing.example.com');
    }

    public function testSuccessfulEnrollmentIsSavedAndReused(): void
    {
        config(['ninja.environment' => 'selfhost', 'ninja.app_url' => 'https://billing.example.com/public']);
        $gateway = $this->gateway();
        $handler = new MockHandler([new Response(204)]);
        $driver = $this->driver($gateway, $handler);
        $this->assertTrue($driver->ensureApplePayDomainIsValidated());
        $this->assertFalse($gateway->isDirty('config'));
        $this->assertTrue($driver->ensureApplePayDomainIsValidated());
        // A later driver instance reads the registration from encrypted gateway configuration.
        $fresh_gateway = $gateway->newQueryWithoutRelationships()->findOrFail($gateway->id);
        $fresh_gateway->setRelation('company', $gateway->company);
        $this->assertTrue($this->driver($fresh_gateway, $handler)->ensureApplePayDomainIsValidated());
        $this->assertCount(0, $handler);
        $this->assertSame('billing.example.com', $gateway->getConfig()->apple_pay_domain_name);
        $this->assertObjectNotHasProperty('apple_pay_domain', $gateway->getConfig());
        $this->assertSame(self::SECRET_KEY, $gateway->getConfig()->secretApiKey);
    }

    public function testChangedDomainReenrolls(): void
    {
        config(['ninja.environment' => 'hosted', 'ninja.app_domain' => 'portal.example.com']);
        $gateway = $this->gateway();
        $gateway->company->portal_mode = 'subdomain';
        $gateway->company->subdomain = 'one';
        $domains = [];
        $driver = $this->driver($gateway, function ($request) use (&$domains) {
            $domains[] = json_decode((string) $request->getBody(), true)['domain'];

            return Create::promiseFor(new Response(204));
        });
        $this->assertTrue($driver->ensureApplePayDomainIsValidated());
        $gateway->company->portal_mode = 'domain';
        $gateway->company->portal_domain = 'https://custom.example.com';
        $this->assertTrue($driver->ensureApplePayDomainIsValidated());
        $this->assertSame(['one.portal.example.com', 'custom.example.com'], $domains);
        $this->assertSame('custom.example.com', $gateway->getConfig()->apple_pay_domain_name);
    }

    public function testFailedEnrollmentIsNotSavedAndNextAttemptRetries(): void
    {
        config(['ninja.environment' => 'selfhost', 'ninja.app_url' => 'https://billing.example.com']);
        $gateway = $this->gateway();
        $driver = $this->driver($gateway, new MockHandler([new Response(503), new Response(204)]));
        $this->assertFalse($driver->ensureApplePayDomainIsValidated());
        $this->assertObjectNotHasProperty('apple_pay_domain_name', $gateway->getConfig());
        $this->assertTrue($driver->ensureApplePayDomainIsValidated());
    }
}
