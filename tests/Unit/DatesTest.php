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

    public function setUp() :void
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
;
    }
}
