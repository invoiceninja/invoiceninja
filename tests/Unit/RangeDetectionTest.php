<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * @test
 */
class RangeDetectionTest extends TestCase
{

    public function setUp() :void
    {
        parent::setUp();
    }

    public function test_range_detection()
    {
        $ranges = [];
        $ranges[] = [100, 105];
        $ranges[] = [106, 110];
        $ranges[] = [110, 115];

        $expanded_ranges = [];

        foreach($ranges as $range)
        {
            $expanded_ranges[] = $this->makeRanges($range);
        }

        foreach($ranges as $range)
        {
            
        }

    }

    private function makeRanges(array $range)
    {

        return range($range[0], $range[1]);

    }

}
