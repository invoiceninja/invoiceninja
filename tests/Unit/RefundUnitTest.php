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

use App\Helpers\Invoice\Refund;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * @test
 */
class RefundUnitTest extends TestCase
{

    public function setUp() :void
    {
        parent::setUp();
    }

    public function testProRataRefundMonthly()
    {
        $r = new Refund();
        $refund = $r->proRata(10, Carbon::parse('2021-01-01'), Carbon::parse('2021-01-31'), RecurringInvoice::FREQUENCY_MONTHLY);

        $this->assertEquals(9.68, $refund);

        $this->assertEquals(30, Carbon::parse('2021-01-01')->diffInDays(Carbon::parse('2021-01-31')));

    }

    public function testProRataRefundYearly()
    {
        $r = new Refund();

        $refund = $r->proRata(10, Carbon::parse('2021-01-01'), Carbon::parse('2021-01-31'), RecurringInvoice::FREQUENCY_ANNUALLY);

        $this->assertEquals(0.82, $refund);
    }

    public function testDiffInDays()
    {

        $this->assertEquals(30, Carbon::parse('2021-01-01')->diffInDays(Carbon::parse('2021-01-31')));

    }

}