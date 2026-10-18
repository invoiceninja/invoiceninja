<?php

namespace Tests\Feature\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Tests\MockAccountData;
use Tests\TestCase;

class StripeAchAuthorizationCustomerTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_GATEWAY_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';

    private CompanyGateway $companyGateway;

    protected function setUp(): void
    {
        parent::setUp();

        Model::reguard();
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'scout.driver' => null,
            'scout.queue' => false,
            'session.driver' => 'array',
        ]);

        $this->makeTestData();

        $this->companyGateway = new CompanyGateway();
        $this->companyGateway->company_id = $this->company->id;
        $this->companyGateway->user_id = $this->user->id;
        $this->companyGateway->gateway_key = self::STRIPE_GATEWAY_KEY;
        $this->companyGateway->config = encrypt(json_encode([
            'apiKey' => 'sk_test_ach_authorization',
            'publishableKey' => 'pk_test_ach_authorization',
        ]));
        $this->companyGateway->save();
    }

    public function testAuthorizationStoresTheSetupIntentCustomerWithoutResolvingItAgain(): void
    {
        $driver = $this->driver();

        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldNotReceive('findOrCreateCustomer');
        $driver->shouldReceive('getSetupIntentId')
            ->once()
            ->with('seti_ach_authorization')
            ->andReturn($this->setupIntent('cus_setup_intent'));
        $driver->shouldReceive('getStripePaymentMethod')
            ->once()
            ->with('pm_ach_authorization')
            ->andReturn($this->paymentMethod('cus_setup_intent'));
        $driver->shouldReceive('getCustomer')
            ->once()
            ->with('cus_setup_intent')
            ->andReturn(Customer::constructFrom(['id' => 'cus_setup_intent']));
        $driver->shouldReceive('storeGatewayToken')
            ->once()
            ->withArgs(function (array $data, array $additional): bool {
                return $data['token'] === 'pm_ach_authorization'
                    && $data['payment_method_id'] === GatewayType::BANK_TRANSFER
                    && $additional['gateway_customer_reference'] === 'cus_setup_intent';
            })
            ->andReturnUsing(function (array $data, array $additional): ClientGatewayToken {
                $token = new ClientGatewayToken();
                $token->company_id = $this->company->id;
                $token->client_id = $this->client->id;
                $token->company_gateway_id = $this->companyGateway->id;
                $token->gateway_type_id = $data['payment_method_id'];
                $token->token = $data['token'];
                $token->gateway_customer_reference = $additional['gateway_customer_reference'];
                $token->meta = $data['payment_meta'];
                $token->save();

                return $token;
            });

        (new ACH($driver))->authorizeResponse($this->request());

        $this->assertDatabaseHas('client_gateway_tokens', [
            'client_id' => $this->client->id,
            'token' => 'pm_ach_authorization',
            'gateway_customer_reference' => 'cus_setup_intent',
        ]);
    }

    public function testAuthorizationRejectsAPaymentMethodBelongingToAnotherCustomer(): void
    {
        $driver = $this->driver();

        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldNotReceive('findOrCreateCustomer');
        $driver->shouldReceive('getSetupIntentId')
            ->once()
            ->with('seti_ach_authorization')
            ->andReturn($this->setupIntent('cus_setup_intent'));
        $driver->shouldReceive('getStripePaymentMethod')
            ->once()
            ->with('pm_ach_authorization')
            ->andReturn($this->paymentMethod('cus_other'));
        $driver->shouldNotReceive('getCustomer');
        $driver->shouldNotReceive('storeGatewayToken');

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('Stripe customer mismatch.');

        (new ACH($driver))->authorizeResponse($this->request());
    }

    private function driver(): StripePaymentDriver
    {
        return Mockery::mock(
            StripePaymentDriver::class,
            [$this->companyGateway, $this->client]
        )->makePartial();
    }

    private function request(): Request
    {
        return Request::create('/client/payment_methods/response', 'POST', [
            'gateway_response' => json_encode([
                'id' => 'seti_ach_authorization',
            ]),
        ]);
    }

    private function setupIntent(string $customer): SetupIntent
    {
        return SetupIntent::constructFrom([
            'id' => 'seti_ach_authorization',
            'status' => 'succeeded',
            'customer' => $customer,
            'payment_method' => 'pm_ach_authorization',
        ]);
    }

    private function paymentMethod(string $customer): PaymentMethod
    {
        return PaymentMethod::constructFrom([
            'id' => 'pm_ach_authorization',
            'customer' => $customer,
            'us_bank_account' => [
                'bank_name' => 'Test Bank',
                'last4' => '6789',
            ],
        ]);
    }
}
