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

namespace Tests\Unit\PaymentDrivers;

use App\Models\CompanyGateway;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\ApiClient;
use Checkout\CheckoutArgumentException;
use Checkout\Client as CheckoutClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\TestCase;

class CheckoutComEndpointTest extends TestCase
{
    private const CURRENT_SECRET_KEY = 'sk_aaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const CURRENT_PUBLIC_KEY = 'pk_aaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const PREVIOUS_SECRET_KEY = 'sk_12345678-1234-1234-1234-123456789012';

    public function testCurrentFramesApiUsesProductionSubdomain(): void
    {
        $driver = $this->makeDriver([
            'secretApiKey' => self::CURRENT_SECRET_KEY,
            'publicApiKey' => '',
            'clientId' => 'cli_vkuhvk4vabcdefghijklmnop',
            'testMode' => false,
        ])->init();

        $this->assertSame(
            'https://vkuhvk4v.api.checkout.com/',
            $this->baseUri($driver->gateway->getPaymentsClient())
        );
    }

    public function testCurrentApiDoesNotRequireClientId(): void
    {
        $driver = $this->makeDriver([
            'secretApiKey' => self::CURRENT_SECRET_KEY,
            'publicApiKey' => self::CURRENT_PUBLIC_KEY,
            'testMode' => false,
        ])->init();

        $this->assertSame(
            'https://api.checkout.com/',
            $this->baseUri($driver->gateway->getPaymentsClient())
        );
    }

    public function testPreviousApiDoesNotRequireClientId(): void
    {
        $driver = $this->makeDriver([
            'secretApiKey' => self::PREVIOUS_SECRET_KEY,
            'publicApiKey' => '',
            'testMode' => false,
        ])->init();

        $this->assertSame(
            'https://api.checkout.com/',
            $this->baseUri($driver->gateway->getPaymentsClient())
        );
    }

    public function testPreviousSandboxApiDoesNotRequireClientId(): void
    {
        $driver = $this->makeDriver([
            'secretApiKey' => self::PREVIOUS_SECRET_KEY,
            'publicApiKey' => '',
            'testMode' => true,
        ])->init();

        $this->assertSame(
            'https://api.sandbox.checkout.com/',
            $this->baseUri($driver->gateway->getPaymentsClient())
        );
    }

    public function testPreviousApiUsesSubdomainWhenClientIdIsPopulated(): void
    {
        $driver = $this->makeDriver([
            'secretApiKey' => self::PREVIOUS_SECRET_KEY,
            'publicApiKey' => '',
            'clientId' => 'cli_vkuhvk4vabcdefghijklmnop',
            'testMode' => false,
        ])->init();

        $this->assertSame(
            'https://vkuhvk4v.api.checkout.com/',
            $this->baseUri($driver->gateway->getPaymentsClient())
        );
    }

    public function testFlowPaymentSessionsUseSandboxSubdomain(): void
    {
        $driver = $this->makeDriver([
            'secretApiKey' => self::CURRENT_SECRET_KEY,
            'publicApiKey' => self::CURRENT_PUBLIC_KEY,
            'clientId' => 'cli_vkuhvk4vabcdefghijklmnop',
            'testMode' => true,
        ])->init();

        $this->assertSame(
            'https://vkuhvk4v.api.sandbox.checkout.com/',
            $this->baseUri($driver->gateway->getPaymentSessionsClient())
        );
    }

    public function testPaymentMethodsProbeUsesSandboxSubdomain(): void
    {
        Http::fake([
            'https://vkuhvk4v.api.sandbox.checkout.com/payment-methods*' => Http::response([
                'methods' => [
                    ['type' => 'ideal'],
                ],
            ]),
        ]);

        $driver = $this->makeDriver([
            'secretApiKey' => self::CURRENT_SECRET_KEY,
            'publicApiKey' => self::CURRENT_PUBLIC_KEY,
            'clientId' => 'cli_vkuhvk4vabcdefghijklmnop',
            'processingChannelId' => 'pc_test',
            'testMode' => true,
        ]);

        $this->assertSame(['ideal'], $driver->probeAvailablePaymentMethods());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://vkuhvk4v.api.sandbox.checkout.com/payment-methods?processing_channel_id=pc_test'
                && $request->hasHeader('Authorization', 'Bearer ' . self::CURRENT_SECRET_KEY);
        });
    }

    public function testPaymentMethodsProbeUsesDefaultSandboxEndpointWithoutClientId(): void
    {
        Http::fake([
            'https://api.sandbox.checkout.com/payment-methods*' => Http::response([
                'methods' => [
                    ['type' => 'ideal'],
                ],
            ]),
        ]);

        $driver = $this->makeDriver([
            'secretApiKey' => self::CURRENT_SECRET_KEY,
            'publicApiKey' => self::CURRENT_PUBLIC_KEY,
            'processingChannelId' => 'pc_test',
            'testMode' => true,
        ]);

        $this->assertSame(['ideal'], $driver->probeAvailablePaymentMethods());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.sandbox.checkout.com/payment-methods?processing_channel_id=pc_test';
        });
    }

    public function testInvalidClientIdIsRejectedBeforeBuildingTheGateway(): void
    {
        $driver = $this->makeDriver([
            'secretApiKey' => self::CURRENT_SECRET_KEY,
            'publicApiKey' => self::CURRENT_PUBLIC_KEY,
            'clientId' => 'cli_short',
            'testMode' => false,
        ]);

        $this->expectException(CheckoutArgumentException::class);
        $this->expectExceptionMessage('Checkout.com client ID must start with cli_');

        $driver->init();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function makeDriver(array $config): CheckoutComPaymentDriver
    {
        $companyGateway = $this->getMockBuilder(CompanyGateway::class)
            ->onlyMethods(['getConfigField', 'save'])
            ->getMock();

        $companyGateway->method('getConfigField')
            ->willReturnCallback(fn(string $field): mixed => $config[$field] ?? null);
        $companyGateway->method('save')->willReturn(true);
        $companyGateway->settings = new \stdClass();

        return new CheckoutComPaymentDriver($companyGateway);
    }

    private function baseUri(object $sdkClient): string
    {
        $apiClientProperty = new ReflectionProperty(CheckoutClient::class, 'apiClient');
        $apiClient = $apiClientProperty->getValue($sdkClient);

        $baseUriProperty = new ReflectionProperty(ApiClient::class, 'baseUri');

        return $baseUriProperty->getValue($apiClient);
    }
}
