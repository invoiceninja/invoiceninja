<?php

namespace Tests\Unit\PaymentDrivers\Stripe;

use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\PaymentDrivers\Stripe\UpdatePaymentMethods;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stripe\ApiRequestor;
use Stripe\Customer;
use Stripe\HttpClient\ClientInterface;

/** No application database or live Stripe requests are used. */
class AchImportVerificationStateTest extends TestCase
{
    private $originalResolver;
    private Manager $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalResolver = Model::getConnectionResolver();
        $this->database = new Manager();
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        Model::setConnectionResolver($this->database->getDatabaseManager());
        $this->database->getConnection()->getSchemaBuilder()->create('client_gateway_tokens', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('client_id');
            $table->integer('company_gateway_id');
            $table->string('gateway_customer_reference');
            $table->string('token');
            $table->text('meta');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        $this->database->getConnection()->disconnect();
        if ($this->originalResolver) {
            Model::setConnectionResolver($this->originalResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        parent::tearDown();
    }

    public static function verificationStates(): array
    {
        return [['unauthorized'], ['pending'], ['inactive']];
    }

    #[DataProvider('verificationStates')]
    public function testImportCannotAuthorizeAnExistingTokenOrDiscardItsVerificationUrl(string $state): void
    {
        $meta = ['brand' => 'Test Bank (ACH)', 'last4' => '0301', 'type' => 2,
            'state' => $state, 'next_action' => 'https://payments.stripe.com/microdeposit/test'];
        $this->database->getConnection()->table('client_gateway_tokens')->insert([
            'id' => 1, 'company_id' => 10, 'client_id' => 20, 'company_gateway_id' => 30,
            'gateway_customer_reference' => 'cus_test', 'token' => 'pm_test',
            'meta' => json_encode($meta),
        ]);

        // Being returned by the customer listing must not authorize a local token.
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())->method('request')->willReturnCallback(
            function ($method, $url, $headers, $params, $hasFile) {
                $this->assertSame('get', strtolower($method));
                $this->assertStringEndsWith('/v1/payment_methods', $url);
                $this->assertSame('cus_test', $params['customer']);
                return [json_encode(['object' => 'list', 'has_more' => false, 'data' => [[
                    'object' => 'payment_method', 'id' => 'pm_test', 'customer' => 'cus_test',
                    'type' => 'us_bank_account', 'livemode' => false,
                    'us_bank_account' => ['bank_name' => 'Test Bank', 'last4' => '0301'],
                ]]]), 200, []];
            }
        );
        ApiRequestor::setHttpClient($http);

        $client = new class extends Client {
            // This test needs only the client's identity, without search observers.
            public static function bootSearchable()
            {
            }
        };
        $client->id = 20;
        $client->company_id = 10;
        $gateway = new CompanyGateway();
        $gateway->id = 30;
        $gateway->company_id = 10;
        $driver = (new ReflectionClass(StripePaymentDriver::class))->newInstanceWithoutConstructor();
        $driver->company_gateway = $gateway;
        $driver->stripe_connect_auth = ['api_key' => 'sk_test_local_only'];

        (new UpdatePaymentMethods($driver))->importPMBankAccounts(
            Customer::constructFrom(['id' => 'cus_test', 'object' => 'customer', 'livemode' => false]),
            $client,
        );

        $this->assertEquals((object) $meta, ClientGatewayToken::findOrFail(1)->meta);
    }
}
