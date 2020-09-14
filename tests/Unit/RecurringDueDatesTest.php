<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace Tests\Unit;

use App\Utils\Traits\Recurring\HasRecurrence;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * @test
 * @covers App\Utils\Traits\Recurring\HasRecurrence
 */
class RecurringDueDatesTest extends TestCase
{

    use HasRecurrence;

    public function setUp() :void
    {

    }

    public function testFirstDate()
    {

        $date = Carbon::parse('2020-02-15');

        $due_date = $this->calculateFirstDayOfMonth($date);

        $this->assertEquals('2020-03-01', $due_date->format('Y-m-d'));

    }
}
