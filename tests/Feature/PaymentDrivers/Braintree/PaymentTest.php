<?php

namespace Tests\Feature\PaymentDrivers\Braintree;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\Braintree\ACH;
use App\PaymentDrivers\Braintree\CreditCard;
use App\PaymentDrivers\Braintree\PayPal;
use App\PaymentDrivers\BraintreePaymentDriver;
use Braintree\Gateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'braintree-test',
            'database.connections.braintree-test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_taxes')->default(0);
            $table->softDeletes();
        });
        Schema::create('client_gateway_tokens', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('client_id');
            $table->integer('company_gateway_id');
            $table->integer('gateway_type_id');
            $table->string('token');
            $table->timestamps();
            $table->softDeletes();
        });
        Bus::fake();
    }

    private function driver(): BraintreePaymentDriver
    {
        $driver = Mockery::mock(BraintreePaymentDriver::class)->makePartial();
        $driver->company_gateway = new CompanyGateway();
        $driver->company_gateway->id = 3;
        $driver->company_gateway->company_id = 4;
        $driver->company_gateway->config = encrypt(json_encode(['threeds' => false, 'merchantAccountId' => 'merchant-usd']));
        $driver->client = Mockery::mock(Client::class)->makePartial();
        $driver->client->id = 2;
        $driver->client->company_id = 4;
        $driver->client->address1 = '200 Market St';
        $driver->client->setRelation('country', null);
        $driver->client->shouldReceive('fresh')->andReturnSelf();
        $driver->client->setRelation('company', new \App\Models\Company());
        $driver->gateway = Mockery::mock(Gateway::class);
        $driver->shouldReceive('init')->andReturnSelf();
        $driver->shouldReceive('findOrCreateCustomer')->andReturn((object) ['id' => 'customer']);
        $driver->shouldReceive('getLevel23Data')->andReturn(['taxExempt' => false]);
        $driver->shouldReceive('logSuccessfulGatewayResponse');
        $driver->shouldReceive('sendFailureMail');
        $driver->payment_hash = Mockery::mock(PaymentHash::class)->makePartial();
        $driver->payment_hash->data = (object) ['amount_with_fee' => '105.00', 'payment_method_id' => 1, 'invoices' => []];
        $driver->payment_hash->setRelation('fee_invoice', new Invoice(['number' => 'INV-1', 'po_number' => 'PO-1']));
        $driver->payment_hash->shouldReceive('save')->andReturnTrue();
        return $driver;
    }

    #[DataProvider('paymentCases')]
    public function testPaymentSubmissionAndVaultConsent(string $method, bool $saved, bool $store, bool $success): void
    {
        $driver = $this->driver();
        $gateway_type_id = match ($method) {
            'card' => GatewayType::CREDIT_CARD, 'paypal' => GatewayType::PAYPAL, default => GatewayType::BANK_TRANSFER
        };
        $payment_type = match ($method) {
            'card' => PaymentType::VISA, 'paypal' => PaymentType::PAYPAL, default => PaymentType::ACH
        };
        $token = new ClientGatewayToken();
        $token->forceFill(['id' => 1, 'company_id' => 4, 'company_gateway_id' => 3, 'client_id' => 2, 'gateway_type_id' => $gateway_type_id, 'token' => 'saved-token']);
        $token->saveQuietly();
        $transaction_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('transaction')->once()->andReturn($transaction_gateway);
        $transaction_gateway->shouldReceive('sale')->once()->with(Mockery::on(function ($data) use ($saved, $store, $method) {
            $this->assertSame('105.00', $data['amount']);
            $this->assertSame('merchant-usd', $data['merchantAccountId']);
            $this->assertTrue($data['options']['submitForSettlement']);
            if ($saved || $method === 'ach') {
                $this->assertSame('saved-token', $data['paymentMethodToken']);
                $this->assertArrayNotHasKey('paymentMethodNonce', $data);
            } else {
                $this->assertSame('new-nonce', $data['paymentMethodNonce']);
                $this->assertArrayNotHasKey('paymentMethodToken', $data);
                $this->assertSame($store, $data['options']['storeInVaultOnSuccess'] ?? false);
            }
            return true;
        }))->andReturn((object) [
            'success' => $success,
            'message' => 'Declined',
            'transaction' => (object) [
                'id' => 'transaction-1',
                'creditCard' => ['cardType' => 'Visa', 'token' => 'new-token'],
                'paypalDetails' => (object) ['token' => 'new-token'],
                'additionalProcessorResponse' => 'Declined',
                'processorResponseCode' => 2000,
            ],
        ]);
        if ($success) {
            $payment = new Payment();
            $payment->id = 1;
            $driver->shouldReceive('createPayment')->once()->with(Mockery::on(function ($data) use ($gateway_type_id, $payment_type) {
                $this->assertSame('105.00', $data['amount']);
                $this->assertSame($gateway_type_id, $data['gateway_type_id']);
                $this->assertSame($payment_type, $data['payment_type']);
                return true;
            }), Payment::STATUS_COMPLETED)->andReturn($payment);
            if ($store && ! $saved && $method !== 'ach') {
                $method_gateway = Mockery::mock();
                $driver->gateway->shouldReceive('paymentMethod')->once()->andReturn($method_gateway);
                $method_gateway->shouldReceive('find')->with('new-token')->andReturn((object) ['token' => 'new-token', 'expirationMonth' => '12', 'expirationYear' => '2030', 'cardType' => 'Visa', 'last4' => '1111', 'email' => 'buyer@example.com']);
                $driver->shouldReceive('storeGatewayToken')->once()->with(Mockery::on(fn($data) => $data['token'] === 'new-token'), ['gateway_customer_reference' => 'customer'])->andReturn(new ClientGatewayToken());
            } else {
                $driver->shouldNotReceive('storeGatewayToken');
            }
        } else {
            $driver->shouldNotReceive('createPayment');
            $driver->shouldNotReceive('storeGatewayToken');
            $this->expectException(PaymentFailed::class);
        }
        $request = PaymentResponseRequest::create('/', 'POST', [
            'gateway_response' => $saved ? null : '{"nonce":"new-nonce"}', 'token' => $saved ? 'saved-token' : null,
            'source' => $token->hashed_id, 'store_card' => $store, 'payment_hash' => 'hash', 'client-data' => 'device-data',
        ]);
        $handler = match ($method) {
            'card' => new CreditCard($driver), 'paypal' => new PayPal($driver), default => new ACH($driver)
        };
        $response = $handler->paymentResponse($request);
        $this->assertTrue($response->isRedirect());
    }

    #[DataProvider('authorizationCases')]
    public function testAchAuthorizationStoresOnlyVerifiedAccounts(bool $success, bool $verified): void
    {
        $driver = $this->driver();
        $method_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('paymentMethod')->once()->andReturn($method_gateway);
        $method_gateway->shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            $this->assertSame('bank-nonce', $data['paymentMethodNonce']);
            $this->assertSame('customer', $data['customerId']);
            $this->assertSame(\Braintree\Result\UsBankAccountVerification::NETWORK_CHECK, $data['options']['usBankAccountVerificationMethod']);
            return true;
        }))->andReturn((object) ['success' => $success, 'paymentMethod' => (object) ['verified' => $verified, 'bankName' => 'Test Bank', 'last4' => '0000', 'token' => 'bank-token']]);
        if ($success && $verified) {
            $driver->shouldReceive('storeGatewayToken')->once()->with(Mockery::on(fn($data) => $data['token'] === 'bank-token' && $data['payment_meta']->state === 'authorized'), ['gateway_customer_reference' => 'customer'])->andReturn(new ClientGatewayToken());
        } else {
            $driver->shouldNotReceive('storeGatewayToken');
        }
        $request = \Illuminate\Http\Request::create('/', 'POST', ['nonce' => 'bank-nonce', 'gateway_type_id' => 2]);
        $response = (new ACH($driver))->authorizeResponse($request);
        $this->assertTrue($response->isRedirect());
    }

    public static function authorizationCases(): array
    {
        return [[true, true], [true, false], [false, false]];
    }

    #[DataProvider('automaticPaymentMethods')]
    public function testAutomaticBillingPreservesPaymentType(int $gateway_type_id, int $payment_type): void
    {
        $driver = $this->driver();
        $token = new ClientGatewayToken();
        $token->token = 'saved-token';
        $token->gateway_type_id = $gateway_type_id;
        $driver->payment_hash->data = (object) ['amount_with_fee' => '105.00', 'invoices' => []];
        $driver->payment_hash->fee_total = 5;
        $presenter = Mockery::mock();
        $presenter->shouldReceive('name')->andReturn('Buyer');
        $driver->client->shouldReceive('present')->andReturn($presenter);
        $driver->shouldReceive('confirmGatewayFee')->once()->with(Mockery::on(fn($data) => $data['gateway_type_id'] === $gateway_type_id));
        $payment = new Payment();
        $driver->shouldReceive('createPayment')->once()->with(Mockery::on(fn($data) => $data['payment_type'] === $payment_type && $data['gateway_type_id'] === $gateway_type_id), Payment::STATUS_COMPLETED)->andReturn($payment);
        $transaction_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('transaction')->once()->andReturn($transaction_gateway);
        $transaction_gateway->shouldReceive('sale')->once()->with(Mockery::on(fn($data) => $data['paymentMethodToken'] === 'saved-token' && $data['merchantAccountId'] === 'merchant-usd'))->andReturn((object) ['success' => true, 'transaction' => (object) ['id' => 'automatic-payment', 'creditCard' => ['cardType' => 'Visa']]]);
        $this->assertSame($payment, $driver->tokenBilling($token, $driver->payment_hash));
    }

    public static function automaticPaymentMethods(): array
    {
        return [[GatewayType::CREDIT_CARD, PaymentType::VISA], [GatewayType::PAYPAL, PaymentType::PAYPAL], [GatewayType::BANK_TRANSFER, PaymentType::ACH]];
    }

    #[DataProvider('savedCardNonceCases')]
    public function testSavedCardAuthenticationPreparation(bool $valid, bool $missing): void
    {
        $driver = $this->driver();
        $driver->company_gateway->config = encrypt(json_encode(['threeds' => true, 'merchantAccountId' => 'merchant-usd']));
        $presenter = Mockery::mock();
        foreach (['email', 'first_name', 'name', 'last_name', 'phone'] as $method) {
            $presenter->shouldReceive($method)->andReturn('Buyer');
        }
        $driver->client->shouldReceive('present')->andReturn($presenter);
        $token = new ClientGatewayToken();
        $token->forceFill(['company_id' => 4, 'company_gateway_id' => 3, 'client_id' => 2, 'gateway_type_id' => 1, 'token' => 'saved-token']);
        $token->saveQuietly();
        $client_token_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('clientToken')->once()->andReturn($client_token_gateway);
        $client_token_gateway->shouldReceive('generate')->once()->with(['merchantAccountId' => 'merchant-usd'])->andReturn('client-token');
        $nonce_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('paymentMethodNonce')->once()->andReturn($nonce_gateway);
        $create_nonce = $nonce_gateway->shouldReceive('create')->once()->with('saved-token');
        if ($missing) {
            $create_nonce->andThrow(new \Braintree\Exception\NotFound());
        } else {
            $create_nonce->andReturn((object) ['success' => $valid, 'paymentMethodNonce' => (object) ['nonce' => 'saved-nonce']]);
        }
        if ($valid) {
            $method_gateway = Mockery::mock();
            $driver->gateway->shouldReceive('paymentMethod')->once()->andReturn($method_gateway);
            $method_gateway->shouldReceive('find')->with('saved-token')->andReturn((object) ['bin' => '411111']);
        }
        $data = (new CreditCard($driver))->paymentData(['tokens' => [$token], 'amount_with_fee' => '105.00']);
        $this->assertSame('client-token', $data['client_token']);
        $this->assertSame('105.00', $data['threeds']['amount']);
        $this->assertCount($valid ? 1 : 0, $data['tokens']);
        $this->assertSame($valid ? ['saved-token' => ['nonce' => 'saved-nonce', 'bin' => '411111']] : [], $data['saved_cards']);
    }

    public static function savedCardNonceCases(): array
    {
        return [[true, false], [false, false], [false, true]];
    }

    #[DataProvider('refundCases')]
    public function testRefundOutcomes(string $outcome): void
    {
        $driver = $this->driver();
        $payment = new Payment();
        $payment->transaction_reference = 'original-transaction';
        $transaction_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('transaction')->once()->andReturn($transaction_gateway);
        $refund = $transaction_gateway->shouldReceive('refund')->once()->with('original-transaction', '10.00');
        if ($outcome === 'exception') {
            $refund->andThrow(new \Exception('Connection failed'));
        } else {
            $errors = Mockery::mock();
            $errors->shouldReceive('deepAll')->andReturn([(object) ['message' => 'Refund declined', 'code' => 2000]]);
            $refund->andReturn((object) ['success' => $outcome === 'success', 'errors' => $errors]);
        }
        $result = $driver->refund($payment, '10.00');
        $this->assertSame($outcome === 'success', $result['success']);
        $this->assertSame($outcome === 'success' ? 'original-transaction' : null, $result['transaction_reference']);
    }

    public static function refundCases(): array
    {
        return [['success'], ['decline'], ['exception']];
    }

    public function testAutomaticBillingDeclineDoesNotRecordPayment(): void
    {
        $driver = $this->driver();
        $token = new ClientGatewayToken();
        $token->token = 'saved-token';
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $presenter = Mockery::mock();
        $presenter->shouldReceive('name')->andReturn('Buyer');
        $driver->client->shouldReceive('present')->andReturn($presenter);
        $driver->shouldNotReceive('createPayment');
        $driver->shouldNotReceive('confirmGatewayFee');
        $transaction_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('transaction')->once()->andReturn($transaction_gateway);
        $transaction_gateway->shouldReceive('sale')->once()->andReturn((object) ['success' => false, 'transaction' => (object) ['additionalProcessorResponse' => 'Declined']]);
        $this->assertFalse($driver->tokenBilling($token, $driver->payment_hash));
    }

    public static function paymentCases(): array
    {
        $cases = [];
        foreach (['card', 'paypal', 'ach'] as $method) {
            foreach ([false, true] as $saved) {
                foreach ([false, true] as $store) {
                    foreach ([false, true] as $success) {
                        if ($method === 'ach' && (! $saved || $store)) {
                            continue;
                        }
                        $cases["{$method},saved={$saved},store={$store},success={$success}"] = [$method, $saved, $store, $success];
                    }
                }
            }
        }
        return $cases;
    }

    #[DataProvider('tokenScopes')]
    public function testStoredTokenCannotCrossOwnershipBoundaries(string $field, int $value): void
    {
        $driver = $this->driver();
        $token = new ClientGatewayToken();
        $token->forceFill(array_merge(['company_id' => 4, 'company_gateway_id' => 3, 'client_id' => 2, 'gateway_type_id' => 1, 'token' => 'saved-token'], [$field => $value]));
        $token->saveQuietly();
        $this->expectException(ModelNotFoundException::class);
        $driver->resolveStoredToken('saved-token', GatewayType::CREDIT_CARD);
    }

    public static function tokenScopes(): array
    {
        return [['company_id', 99], ['company_gateway_id', 99], ['client_id', 99], ['gateway_type_id', 3]];
    }
}
