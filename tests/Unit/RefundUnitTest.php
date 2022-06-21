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

use App\Helpers\Invoice\ProRata;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * @test
 */
class RefundUnitTest extends TestCase
{
    protected function setUp() :void
    {
        parent::setUp();
    }

    // public function testProRataRefundMonthly()
    // {
    //     $pro_rata = new ProRata();
    //     $refund = $pro_rata->refund(10, Carbon::parse('2021-01-01'), Carbon::parse('2021-01-31'), RecurringInvoice::FREQUENCY_MONTHLY);

    //     $this->assertEquals(9.68, $refund);

    //     $this->assertEquals(30, Carbon::parse('2021-01-01')->diffInDays(Carbon::parse('2021-01-31')));

    // }

    // public function testProRataRefundYearly()
    // {
    //     $pro_rata = new ProRata();

    //     $refund = $pro_rata->refund(10, Carbon::parse('2021-01-01'), Carbon::parse('2021-01-31'), RecurringInvoice::FREQUENCY_ANNUALLY);

    //     $this->assertEquals(0.82, $refund);
    // }

    public function testDiffInDays()
    {
        $this->assertEquals(30, Carbon::parse('2021-01-01')->diffInDays(Carbon::parse('2021-01-31')));
    }
}
