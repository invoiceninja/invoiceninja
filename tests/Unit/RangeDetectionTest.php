<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use Tests\TestCase;

/**
 * @test
 */
class RangeDetectionTest extends TestCase
{
    protected function setUp(): void
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

        foreach ($ranges as $range) {
            $expanded_ranges = array_merge(array_values($expanded_ranges), array_values($this->makeRanges($range)));
        }

        $value_count_array = array_count_values($expanded_ranges);

        $value_count_array = array_diff($value_count_array, [1]);

        $this->assertEquals(count($value_count_array), 1);
    }

    private function makeRanges(array $range)
    {
        return range($range[0], $range[1]);
    }
}
