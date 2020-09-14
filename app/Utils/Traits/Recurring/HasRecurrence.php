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

namespace App\Utils\Traits\Recurring;

use Illuminate\Support\Carbon;

trait HasRecurrence
{

    /**
     * Calculates the first day of the month, this will ALWAYS 
     * be the first of NEXT month
     * 
     * @param  Carbon $date The given date
     * @return Carbon       The first of NEXT month 
     */
    public function calculateFirstDayOfMonth($date)
    {
        return $date->copy()->startOfMonth()->addMonth();
    }

    /**
     * Calculates the last day of the month.
     *
     * If it is the last day of the month - we add a month on.
     * 
     * @param  Carbon $date The start date
     * @return Carbon       The last day of month
     */
    public function calculateLastDayOfMonth($date)
    {
        if($date->isLastOfMonth())
            return $date->copy()->endOfMonth()->addMonthNoOverflow();

        return $date->copy()->endOfMonth();
    }

    /**
     * Sets the day of the month, if in the past we ADD a month
     *     
     * @param Carbon $date              The start date
     * @param String|Int $day_of_month  The day of the month
     */
    public function setDateOfMonth($date, $day_of_month)
    {

        $set_date = $date->copy()->setUnitNoOverflow('day', $day_of_month, 'month');

        if($set_date->isPast())
            return $set_date->addMonthNoOverflow();

        return $set_date;
    }

}