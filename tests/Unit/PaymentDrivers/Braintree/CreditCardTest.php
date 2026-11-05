<?php

namespace Tests\Unit\PaymentDrivers\Braintree;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\Braintree\CreditCard;
use App\PaymentDrivers\BraintreePaymentDriver;
use Braintree\Gateway;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreditCardTest extends TestCase
{
    #[DataProvider('paymentSources')]
    public function testPaymentSourcePreservesAuthenticationAndVaultConsent(bool $threeds, bool $saved, bool $store): void
    {
        $driver = Mockery::mock(BraintreePaymentDriver::class)->makePartial();
        $driver->company_gateway = new CompanyGateway();
        $driver->company_gateway->config = encrypt(json_encode(['threeds' => $threeds]));
        $driver->gateway = Mockery::mock(Gateway::class);
        $driver->shouldReceive('init')->once()->andReturnSelf();

        if ($saved) {
            $driver->shouldReceive('resolveStoredToken')->once()->with('saved-token', GatewayType::CREDIT_CARD)->andReturn(new ClientGatewayToken());
        }
        if ($threeds) {
            $nonce_gateway = Mockery::mock();
            $driver->gateway->shouldReceive('paymentMethodNonce')->once()->andReturn($nonce_gateway);
            $nonce_gateway->shouldReceive('find')->once()->with('verified-nonce')->andReturn((object) ['threeDSecureInfo' => (object) ['liabilityShifted' => true]]);
        }

        $request = PaymentResponseRequest::create('/', 'POST', [
            'token' => $saved ? 'saved-token' : null,
            'store_card' => $store,
            'gateway_response' => json_encode(['nonce' => 'verified-nonce', 'liabilityShifted' => false]),
        ]);
        $card = new CreditCard($driver);
        $source = (new \ReflectionMethod($card, 'paymentSource'))->invoke($card, $request, 'customer');

        if ($saved && ! $threeds) {
            $this->assertSame(['paymentMethodToken' => 'saved-token'], $source);
            return;
        }
        $this->assertSame('verified-nonce', $source['paymentMethodNonce']);
        $this->assertSame($threeds, $source['options']['threeDSecure']['required']);
        $this->assertArrayNotHasKey('paymentMethodToken', $source);
        if ($store && ! $saved) {
            $this->assertSame('customer', $source['customerId']);
            $this->assertTrue($source['options']['storeInVaultOnSuccess']);
        } else {
            $this->assertArrayNotHasKey('customerId', $source);
            $this->assertArrayNotHasKey('storeInVaultOnSuccess', $source['options']);
        }
    }

    public static function paymentSources(): array
    {
        $cases = [];
        foreach ([false, true] as $threeds) {
            foreach ([false, true] as $saved) {
                foreach ([false, true] as $store) {
                    $cases["3ds={$threeds},saved={$saved},store={$store}"] = [$threeds, $saved, $store];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('invalidNonces')]
    public function testRejectsMissingOrMalformedNonce(?string $response): void
    {
        $driver = Mockery::mock(BraintreePaymentDriver::class)->makePartial();
        $driver->company_gateway = new CompanyGateway();
        $driver->company_gateway->config = encrypt(json_encode(['threeds' => false]));
        $driver->shouldReceive('init')->andReturnSelf();
        $card = new CreditCard($driver);
        $request = PaymentResponseRequest::create('/', 'POST', ['gateway_response' => $response]);
        $this->expectException(PaymentFailed::class);
        (new \ReflectionMethod($card, 'paymentSource'))->invoke($card, $request, 'customer');
    }

    public static function invalidNonces(): array
    {
        return [[null], [''], ['invalid'], ['{}'], ['{"nonce":""}'], ['{"nonce":123}']];
    }

    public function testRejectsBrowserSpoofedLiabilityShift(): void
    {
        $driver = Mockery::mock(BraintreePaymentDriver::class)->makePartial();
        $driver->company_gateway = new CompanyGateway();
        $driver->company_gateway->config = encrypt(json_encode(['threeds' => true]));
        $driver->gateway = Mockery::mock(Gateway::class);
        $driver->shouldReceive('init')->andReturnSelf();
        $nonce_gateway = Mockery::mock();
        $driver->gateway->shouldReceive('paymentMethodNonce')->andReturn($nonce_gateway);
        $nonce_gateway->shouldReceive('find')->with('unverified')->andReturn((object) ['threeDSecureInfo' => null]);
        $card = new CreditCard($driver);
        $request = PaymentResponseRequest::create('/', 'POST', ['gateway_response' => '{"nonce":"unverified","liabilityShifted":true}']);
        $this->expectException(PaymentFailed::class);
        (new \ReflectionMethod($card, 'paymentSource'))->invoke($card, $request, 'customer');
    }
}
