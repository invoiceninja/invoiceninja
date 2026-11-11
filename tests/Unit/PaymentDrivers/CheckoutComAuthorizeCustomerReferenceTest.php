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
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\PaymentDrivers\CheckoutCom\CreditCard;
use App\PaymentDrivers\CheckoutCom\CreditCardFlow;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Illuminate\Http\Request;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class CheckoutComAuthorizeCustomerReferenceTest extends TestCase
{
    public function testFramesAuthorizePrefersPaymentCustomerId(): void
    {
        $driver = $this->makeAuthorizeDriver(
            paymentCustomerId: 'cus_payment',
            getCustomerId: 'cus_lookup',
        );

        $request = Request::create('/authorize', 'POST', [
            'gateway_response' => json_encode(['token' => 'tok_test']),
        ]);

        try {
            (new CreditCard($driver))->authorizeResponse($request);
            $this->fail('Expected storeGatewayToken short-circuit');
        } catch (RuntimeException $e) {
            $this->assertSame('captured', $e->getMessage());
        }

        $this->assertSame('cus_payment', $driver->storedAdditional['gateway_customer_reference'] ?? null);
    }

    public function testFlowAuthorizePrefersPaymentCustomerId(): void
    {
        $driver = $this->makeAuthorizeDriver(
            paymentCustomerId: 'cus_payment',
            getCustomerId: 'cus_lookup',
        );

        $method = new ReflectionMethod(CreditCardFlow::class, 'authorizeResponseFlow');
        $method->setAccessible(true);

        try {
            $method->invoke(new CreditCardFlow($driver), 'pay_test');
            $this->fail('Expected storeGatewayToken short-circuit');
        } catch (RuntimeException $e) {
            $this->assertSame('captured', $e->getMessage());
        }

        $this->assertSame('cus_payment', $driver->storedAdditional['gateway_customer_reference'] ?? null);
    }

    public function testFlowAuthorizeFallsBackToGetCustomerId(): void
    {
        $driver = $this->makeAuthorizeDriver(
            paymentCustomerId: null,
            getCustomerId: 'cus_lookup',
        );

        $method = new ReflectionMethod(CreditCardFlow::class, 'authorizeResponseFlow');
        $method->setAccessible(true);

        try {
            $method->invoke(new CreditCardFlow($driver), 'pay_test');
            $this->fail('Expected storeGatewayToken short-circuit');
        } catch (RuntimeException $e) {
            $this->assertSame('captured', $e->getMessage());
        }

        $this->assertSame('cus_lookup', $driver->storedAdditional['gateway_customer_reference'] ?? null);
    }

    private function makeAuthorizeDriver(
        ?string $paymentCustomerId,
        string $getCustomerId
    ): CheckoutComPaymentDriver {
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCurrencyCode'])
            ->getMock();
        $client->method('getCurrencyCode')->willReturn('USD');

        return new class (new CompanyGateway(), $client, $paymentCustomerId, $getCustomerId) extends CheckoutComPaymentDriver {
            public array $storedAdditional = [];

            public function __construct(
                CompanyGateway $company_gateway,
                $client,
                public readonly ?string $paymentCustomerId,
                private readonly string $getCustomerId
            ) {
                parent::__construct($company_gateway, $client);
                $this->is_four_api = false;

                $outer = $this;
                $this->gateway = new class ($outer) {
                    public function __construct(private CheckoutComPaymentDriver $driver) {}

                    public function getPaymentsClient()
                    {
                        $driver = $this->driver;

                        return new class ($driver) {
                            public function __construct(private CheckoutComPaymentDriver $driver) {}

                            public function requestPayment($request)
                            {
                                return $this->driver->paymentPayload();
                            }

                            public function getPaymentDetails($paymentId)
                            {
                                return $this->driver->paymentPayload();
                            }

                            public function voidPayment($paymentId)
                            {
                                return [];
                            }
                        };
                    }
                };
            }

            public function paymentPayload(): array
            {
                $customer = [];
                if ($this->paymentCustomerId !== null) {
                    $customer['id'] = $this->paymentCustomerId;
                }

                return [
                    'approved' => true,
                    'status' => 'Authorized',
                    'source' => [
                        'id' => 'src_test',
                        'expiry_month' => 12,
                        'expiry_year' => 2030,
                        'scheme' => 'VISA',
                        'last4' => '4242',
                    ],
                    'customer' => $customer,
                ];
            }

            public function init()
            {
                return $this;
            }

            public function getCustomer()
            {
                return ['id' => $this->getCustomerId];
            }

            public function storeGatewayToken(array $data, array $additional = []): ?ClientGatewayToken
            {
                $this->storedAdditional = $additional;

                throw new RuntimeException('captured');
            }
        };
    }
}
