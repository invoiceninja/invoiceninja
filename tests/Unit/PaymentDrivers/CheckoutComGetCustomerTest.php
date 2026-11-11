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
use App\Models\CompanyGateway;
use App\Models\Presenters\ClientPresenter;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use PHPUnit\Framework\TestCase;

class CheckoutComGetCustomerTest extends TestCase
{
    public function testGetCustomerUsesStoredReferenceBeforeEmail(): void
    {
        $driver = $this->makeDriver('cus_stored', 'client@gmail.com');

        $this->assertSame(['id' => 'cus_stored'], $driver->getCustomer());
        $this->assertSame(['cus_stored'], $driver->lookups);
        $this->assertFalse($driver->created);
    }

    public function testGetCustomerFallsBackToEmailWhenNoReference(): void
    {
        $driver = $this->makeDriver(null, 'client@gmail.com');

        $this->assertSame(['id' => 'client@gmail.com'], $driver->getCustomer());
        $this->assertSame(['client@gmail.com'], $driver->lookups);
        $this->assertFalse($driver->created);
    }

    public function testGetCustomerFallsBackToEmailWhenStoredReferenceMisses(): void
    {
        $driver = $this->makeDriver('cus_stale', 'client@gmail.com', miss: ['cus_stale']);

        $this->assertSame(['id' => 'client@gmail.com'], $driver->getCustomer());
        $this->assertSame(['cus_stale', 'client@gmail.com'], $driver->lookups);
        $this->assertFalse($driver->created);
    }

    public function testGetCustomerCreatesWhenReferenceAndEmailMiss(): void
    {
        $driver = $this->makeDriver('cus_stale', 'client@gmail.com', miss: ['cus_stale', 'client@gmail.com']);

        $this->assertSame(['id' => 'cus_created'], $driver->getCustomer());
        $this->assertSame(['cus_stale', 'client@gmail.com'], $driver->lookups);
        $this->assertTrue($driver->created);
    }

    public function testGetCustomerCreatesWhenNoReferenceAndEmailMisses(): void
    {
        $driver = $this->makeDriver(null, 'client@gmail.com', miss: ['client@gmail.com']);

        $this->assertSame(['id' => 'cus_created'], $driver->getCustomer());
        $this->assertSame(['client@gmail.com'], $driver->lookups);
        $this->assertTrue($driver->created);
    }

    /**
     * @param list<string> $miss
     */
    private function makeDriver(?string $reference, string $email, array $miss = []): CheckoutComPaymentDriver
    {
        $presenter = $this->createMock(ClientPresenter::class);
        $presenter->method('email')->willReturn($email);
        $presenter->method('name')->willReturn('Test Client');
        $presenter->method('phone')->willReturn('5551234567');

        $client = $this->createMock(Client::class);
        $client->method('present')->willReturn($presenter);

        return new class (new CompanyGateway(), $client, $reference, $miss) extends CheckoutComPaymentDriver {
            /** @var list<string> */
            public array $lookups = [];

            public bool $created = false;

            public function __construct(
                CompanyGateway $company_gateway,
                $client,
                private readonly ?string $resolvedReference,
                /** @var list<string> */
                public readonly array $missLookups
            ) {
                parent::__construct($company_gateway, $client);

                $outer = $this;
                $this->gateway = new class ($outer) {
                    public function __construct(private CheckoutComPaymentDriver $driver) {}

                    public function getCustomersClient()
                    {
                        $driver = $this->driver;

                        return new class ($driver) {
                            public function __construct(private CheckoutComPaymentDriver $driver) {}

                            public function get($identifier)
                            {
                                $this->driver->lookups[] = $identifier;

                                if (in_array($identifier, $this->driver->missLookups, true)) {
                                    throw new \Exception('not found');
                                }

                                return ['id' => $identifier];
                            }

                            public function create($request)
                            {
                                $this->driver->created = true;

                                return ['id' => 'cus_created'];
                            }
                        };
                    }
                };
            }

            public function resolveGatewayCustomerReference(): ?string
            {
                return $this->resolvedReference;
            }
        };
    }
}
