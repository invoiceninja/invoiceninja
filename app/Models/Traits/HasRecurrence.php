<?php

namespace App\Models\Traits;

use Carbon;
use DateTime;
use Utils;

/**
 * Class HasRecurrence
 */
trait HasRecurrence
{
    /**
     * @return bool
     */
    public function shouldSendToday()
    {
        if (! $this->user->confirmed) {
            return false;
        }

        $account = $this->account;
        $timezone = $account->getTimezone();

        if (! $this->start_date || Carbon::parse($this->start_date, $timezone)->isFuture()) {
            return false;
        }

        if ($this->end_date && Carbon::parse($this->end_date, $timezone)->isPast()) {
            return false;
        }

        if (! $this->last_sent_date) {
            return true;
        } else {
            $date1 = new DateTime($this->last_sent_date);
            $date2 = new DateTime();
            $diff = $date2->diff($date1);
            $daysSinceLastSent = $diff->format('%a');
            $monthsSinceLastSent = ($diff->format('%y') * 12) + $diff->format('%m');

            // check we don't send a few hours early due to timezone difference
            if (Utils::isNinja() && Carbon::now()->format('Y-m-d') != Carbon::now($timezone)->format('Y-m-d')) {
                return false;
            }

            // check we never send twice on one day
            if ($daysSinceLastSent == 0) {
                return false;
            }
        }

        switch ($this->frequency_id) {
            case FREQUENCY_WEEKLY:
                return $daysSinceLastSent >= 7;
            case FREQUENCY_TWO_WEEKS:
                return $daysSinceLastSent >= 14;
            case FREQUENCY_FOUR_WEEKS:
                return $daysSinceLastSent >= 28;
            case FREQUENCY_MONTHLY:
                return $monthsSinceLastSent >= 1;
            case FREQUENCY_TWO_MONTHS:
                return $monthsSinceLastSent >= 2;
            case FREQUENCY_THREE_MONTHS:
                return $monthsSinceLastSent >= 3;
            case FREQUENCY_FOUR_MONTHS:
                return $monthsSinceLastSent >= 4;
            case FREQUENCY_SIX_MONTHS:
                return $monthsSinceLastSent >= 6;
            case FREQUENCY_ANNUALLY:
                return $monthsSinceLastSent >= 12;
            case FREQUENCY_TWO_YEARS:
                return $monthsSinceLastSent >= 24;
            default:
                return false;
        }

        return false;
    }

    /**
     * @return string
     */
    private function getRecurrenceRule()
    {
        $rule = '';

        switch ($this->frequency_id) {
            case FREQUENCY_WEEKLY:
                $rule = 'FREQ=WEEKLY;';
                break;
            case FREQUENCY_TWO_WEEKS:
                $rule = 'FREQ=WEEKLY;INTERVAL=2;';
                break;
            case FREQUENCY_FOUR_WEEKS:
                $rule = 'FREQ=WEEKLY;INTERVAL=4;';
                break;
            case FREQUENCY_MONTHLY:
                $rule = 'FREQ=MONTHLY;';
                break;
            case FREQUENCY_TWO_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=2;';
                break;
            case FREQUENCY_THREE_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=3;';
                break;
            case FREQUENCY_FOUR_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=4;';
                break;
            case FREQUENCY_SIX_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=6;';
                break;
            case FREQUENCY_ANNUALLY:
                $rule = 'FREQ=YEARLY;';
                break;
            case FREQUENCY_TWO_YEARS:
                $rule = 'FREQ=YEARLY;INTERVAL=2;';
                break;
        }

        if ($this->end_date) {
            $rule .= 'UNTIL=' . $this->getOriginal('end_date');
        }

        return $rule;
    }

    /*
    public function shouldSendToday()
    {
        if (!$nextSendDate = $this->getNextSendDate()) {
            return false;
        }

        return $this->account->getDateTime() >= $nextSendDate;
    }
    */

}
