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

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Presenters\ClientPresenter;
use App\PaymentDrivers\CheckoutCom\CreditCardFlow;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\CheckoutApiException;
use Checkout\HttpMetadata;
use Tests\TestCase;

class CheckoutComFlowSessionRetryTest extends TestCase
{
    public function testMerchantReferencePreservedWhenCustomerIdPinned(): void
    {
        $driver = $this->makeDriver('cus_stored');

        (new CreditCardFlow($driver))->createPaymentSession(
            1000,
            'invoice-ref',
            'hash',
            'https://example.com/success',
            'https://example.com/failure'
        );

        $this->assertSame(['cus_stored'], $driver->sessionCustomerIds);
        $this->assertSame(['invoice-ref'], $driver->sessionReferences);
    }

    public function testMerchantReferencePreservedWithoutCustomerId(): void
    {
        $driver = $this->makeDriver(null);

        (new CreditCardFlow($driver))->createPaymentSession(
            1000,
            'invoice-ref',
            'hash',
            'https://example.com/success',
            'https://example.com/failure'
        );

        $this->assertSame([null], $driver->sessionCustomerIds);
        $this->assertSame(['invoice-ref'], $driver->sessionReferences);
    }

    public function testRetriesWithoutCustomerIdOnStaleCustomerError(): void
    {
        $driver = $this->makeDriver('cus_stale', firstError: $this->checkoutApiException(['customer_not_found']));

        (new CreditCardFlow($driver))->createPaymentSession(
            1000,
            'invoice-ref',
            'hash',
            'https://example.com/success',
            'https://example.com/failure'
        );

        $this->assertSame(['cus_stale', null], $driver->sessionCustomerIds);
        $this->assertSame(['invoice-ref', 'invoice-ref'], $driver->sessionReferences);
    }

    public function testDoesNotRetryOnNonCustomerError(): void
    {
        $driver = $this->makeDriver(
            'cus_stored',
            firstError: $this->checkoutApiException(['processing_channel_id_invalid'])
        );

        try {
            (new CreditCardFlow($driver))->createPaymentSession(
                1000,
                'invoice-ref',
                'hash',
                'https://example.com/success',
                'https://example.com/failure'
            );
            $this->fail('Expected CheckoutApiException');
        } catch (CheckoutApiException $e) {
            $this->assertSame(['cus_stored'], $driver->sessionCustomerIds);
            $this->assertSame(['invoice-ref'], $driver->sessionReferences);
        }
    }

    public function testDoesNotRetryWhenNoCustomerIdPinned(): void
    {
        $driver = $this->makeDriver(null, firstError: $this->checkoutApiException(['customer_not_found']));

        $this->expectException(CheckoutApiException::class);

        (new CreditCardFlow($driver))->createPaymentSession(
            1000,
            'invoice-ref',
            'hash',
            'https://example.com/success',
            'https://example.com/failure'
        );
    }

    private function checkoutApiException(array $errorCodes, int $status = 422): CheckoutApiException
    {
        $exception = new CheckoutApiException('checkout api error');
        $exception->error_details = ['error_codes' => $errorCodes];
        $exception->http_metadata = new HttpMetadata('error', $status, [], '1.1');

        return $exception;
    }

    private function makeDriver(?string $customerId, ?CheckoutApiException $firstError = null): CheckoutComPaymentDriver
    {
        $presenter = $this->createMock(ClientPresenter::class);
        $presenter->method('email')->willReturn('client@gmail.com');
        $presenter->method('name')->willReturn('Test Client');

        $company = new Company();
        $company->settings = (object) ['country_id' => null];

        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['present', 'getCurrencyCode'])
            ->getMock();
        $client->method('present')->willReturn($presenter);
        $client->method('getCurrencyCode')->willReturn('USD');
        $client->address1 = '1 Main';
        $client->city = 'Town';
        $client->state = 'CA';
        $client->postal_code = '90001';
        $client->country = (object) ['iso_3166_2' => 'US'];
        $client->company = $company;

        $companyGateway = $this->getMockBuilder(CompanyGateway::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfigField'])
            ->getMock();
        $companyGateway->method('getConfigField')->willReturn('pc_test');

        $driver = new class (new CompanyGateway(), $client, $customerId, $firstError) extends CheckoutComPaymentDriver {
            /** @var list<?string> */
            public array $sessionCustomerIds = [];

            /** @var list<?string> */
            public array $sessionReferences = [];

            public function __construct(
                CompanyGateway $company_gateway,
                $client,
                private readonly ?string $resolvedReference,
                public readonly ?CheckoutApiException $firstError
            ) {
                parent::__construct($company_gateway, $client);

                $outer = $this;
                $this->gateway = new class ($outer) {
                    public function __construct(private CheckoutComPaymentDriver $driver) {}

                    public function getPaymentSessionsClient()
                    {
                        $driver = $this->driver;

                        return new class ($driver) {
                            public function __construct(private CheckoutComPaymentDriver $driver) {}

                            public function createPaymentSessions($request)
                            {
                                $this->driver->sessionCustomerIds[] = $request->customer->id ?? null;
                                $this->driver->sessionReferences[] = $request->reference ?? null;

                                if (
                                    $this->driver->firstError
                                    && count($this->driver->sessionCustomerIds) === 1
                                ) {
                                    throw $this->driver->firstError;
                                }

                                return ['id' => 'ps_test'];
                            }
                        };
                    }
                };
            }

            public function init()
            {
                return $this;
            }

            public function resolveGatewayCustomerReference(): ?string
            {
                return $this->resolvedReference;
            }
        };

        $driver->company_gateway = $companyGateway;
        $driver->client = $client;
        $driver->gateway_type_id = 1;

        return $driver;
    }
}
