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

use App\Helpers\Invoice\InvoiceSum;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class DatesTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        // $this->makeTestData();
    }

    public function testDaysDiff()
    {
        $string_date = '2021-06-01';

        $start_date = Carbon::parse($string_date);
        $current_date = Carbon::parse('2021-06-20');

        $diff_in_days = $start_date->diffInDays($current_date);

        $this->assertEquals(19, $diff_in_days);
    }

    public function testDiffInDaysRange()
    {
        $now = Carbon::parse('2020-01-01');

        $x = now()->diffInDays(now()->addDays(7));

        $this->assertEquals(7, $x);
    }

    public function testFourteenDaysFromNow()
    {
        $date_in_past = '2020-01-01';

        $date_in_future = Carbon::parse('2020-01-16');

        $this->assertTrue($date_in_future->gt(Carbon::parse($date_in_past)->addDays(14)));
    }

    public function testThirteenteenDaysFromNow()
    {
        $date_in_past = '2020-01-01';

        $date_in_future = Carbon::parse('2020-01-15');

        $this->assertFalse($date_in_future->gt(Carbon::parse($date_in_past)->addDays(14)));
    }
}
