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
        $refund = $this->proRataRefund(10, Carbon::parse('2021-01-01'), Carbon::parse('2021-01-31'), RecurringInvoice::FREQUENCY_MONTHLY);

        $this->assertEquals(9.68, $refund);

        $this->assertEquals(30, Carbon::parse('2021-01-01')->diffInDays(Carbon::parse('2021-01-31')));

    }

    public function testProRataRefundYearly()
    {

        $refund = $this->proRataRefund(10, Carbon::parse('2021-01-01'), Carbon::parse('2021-01-31'), RecurringInvoice::FREQUENCY_ANNUALLY);

        $this->assertEquals(0.82, $refund);
    }

    public function testDiffInDays()
    {

        $this->assertEquals(30, Carbon::parse('2021-01-01')->diffInDays(Carbon::parse('2021-01-31')));

    }

    private function proRataRefund(float $amount, Carbon $from_date, Carbon $to_date, int $frequency) :float
    {
        $days = $from_date->diffInDays($to_date);
        $days_in_frequency = $this->getDaysInFrequencySpecific($frequency);

        return round( (($days/$days_in_frequency) * $amount),2);
    }

    private function getDaysInFrequencySpecific($frequency)
    {

        switch ($frequency) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return 1;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return 7;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return 14;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return now()->diffInDays(now()->addWeeks(4));
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return now()->diffInDays(now()->addMonthNoOverflow());
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(2));
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(3));
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(4));
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(6));
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return now()->diffInDays(now()->addYear());
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return now()->diffInDays(now()->addYears(2));
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return now()->diffInDays(now()->addYears(3));
            default:
                return 0;
        }
    }

}