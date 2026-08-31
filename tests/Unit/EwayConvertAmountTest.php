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

namespace Tests\Unit;

use App\PaymentDrivers\EwayPaymentDriver;
use App\PaymentDrivers\SquarePaymentDriver;
use App\Utils\BcMath;
use Tests\TestCase;

class EwayConvertAmountTest extends TestCase
{
    public function testConvertAmountUsesCurrencyPrecision(): void
    {
        $driver = $this->driverWithoutConstructor(EwayPaymentDriver::class);
        $driver->client = $this->clientWithCurrency('USD', 2);

        $this->assertSame(1029, $driver->convertAmount(10.29));
    }

    public function testZeroDecimalCurrencyDoesNotMultiplyByOneHundred(): void
    {
        $driver = $this->driverWithoutConstructor(EwayPaymentDriver::class);
        $driver->client = $this->clientWithCurrency('JPY', 2);

        $this->assertSame(1050, $driver->convertAmount(1050));
        $this->assertSame(BcMath::toMinorUnits(1050, 0), $driver->convertAmount(1050));
    }

    public function testSquareConvertAmountUsesCurrencyPrecision(): void
    {
        $driver = $this->driverWithoutConstructor(SquarePaymentDriver::class);
        $driver->client = $this->clientWithCurrency('USD', 2);

        $this->assertSame(1029, $driver->convertAmount(10.29));
    }

    /**
     * @template T of EwayPaymentDriver|SquarePaymentDriver
     * @param  class-string<T>  $class
     * @return T
     */
    private function driverWithoutConstructor(string $class): EwayPaymentDriver|SquarePaymentDriver
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function clientWithCurrency(string $code, int $precision): object
    {
        return new class($code, $precision)
        {
            public function __construct(private string $code, private int $precision) {}

            public function currency(): object
            {
                return (object) [
                    'code' => $this->code,
                    'precision' => $this->precision,
                ];
            }
        };
    }
}
