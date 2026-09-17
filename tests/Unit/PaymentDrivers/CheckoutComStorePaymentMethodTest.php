<?php

namespace Tests\Unit\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use PHPUnit\Framework\TestCase;

class CheckoutComStorePaymentMethodTest extends TestCase
{
    public function testStorePaymentMethodForwardsAdditionalTokenAttributes(): void
    {
        $driver = new class (new CompanyGateway()) extends CheckoutComPaymentDriver {
            public array $storedData = [];

            public array $storedAdditional = [];

            public function storeGatewayToken(array $data, array $additional = []): ?ClientGatewayToken
            {
                $this->storedData = $data;
                $this->storedAdditional = $additional;

                return new ClientGatewayToken();
            }
        };

        $data = [
            'token' => 'src_test',
            'payment_method_id' => 1,
            'payment_meta' => new \stdClass(),
        ];
        $additional = ['gateway_customer_reference' => 'cus_test'];

        $token = $driver->storePaymentMethod($data, $additional);

        $this->assertInstanceOf(ClientGatewayToken::class, $token);
        $this->assertSame($data, $driver->storedData);
        $this->assertSame($additional, $driver->storedAdditional);
    }
}
