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

use App\Utils\BcMath;
use Tests\TestCase;

class BcMathToMinorUnitsTest extends TestCase
{
    public function testTwoDecimalPrecision(): void
    {
        $this->assertSame(1029, BcMath::toMinorUnits('10.29', 2));
        $this->assertSame(100, BcMath::toMinorUnits('1.00', 2));
        $this->assertSame(10000, BcMath::toMinorUnits('100.00', 2));
    }

    public function testFloatEdgeCaseAvoidsMinorUnitDrift(): void
    {
        $this->assertSame(1029, BcMath::toMinorUnits(10.29, 2));
    }

    public function testZeroDecimalPrecision(): void
    {
        $this->assertSame(1050, BcMath::toMinorUnits('1050', 0));
        $this->assertSame(1050, BcMath::toMinorUnits(1050.4, 0));
        $this->assertSame(1051, BcMath::toMinorUnits(1050.5, 0));
    }

    public function testOneDecimalPrecision(): void
    {
        $this->assertSame(105, BcMath::toMinorUnits('10.5', 1));
    }
}
