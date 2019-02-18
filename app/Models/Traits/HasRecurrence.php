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
        if (Utils::isSelfHost()) {
            return $this->shouldSendTodayNew();
        } else {
            return $this->shouldSendTodayOld();
        }
    }

    /**
     * @return bool
     */
    public function shouldSendTodayOld()
    {
        if (! $this->user->confirmed) {
            return false;
        }

        $account = $this->account;
        $timezone = $account->getTimezone();

        if (! $this->start_date || Carbon::parse($this->start_date, $timezone)->isFuture()) {
            return false;
        }

        if ($this->end_date && Carbon::parse($this->end_date, $timezone)->isPast()
            && ! Carbon::parse($this->end_date, $timezone)->isToday()) {
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

    public function shouldSendTodayNew()
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
            // check we don't send a few hours early due to timezone difference
            if (Utils::isNinja() && Carbon::now()->format('Y-m-d') != Carbon::now($timezone)->format('Y-m-d')) {
                return false;
            }

            $nextSendDate = $this->getNextSendDate();

            if (! $nextSendDate) {
                return false;
            }

            return $this->account->getDateTime() >= $nextSendDate;
        }
    }

    /**
     * @throws \Recurr\Exception\MissingData
     *
     * @return bool|\Recurr\RecurrenceCollection
     */
    public function getSchedule()
    {
        if (! $this->start_date || ! $this->frequency_id) {
            return false;
        }

        $startDate = $this->getOriginal('last_sent_date') ?: $this->getOriginal('start_date');
        $startDate .= ' ' . $this->account->recurring_hour . ':00:00';
        $timezone = $this->account->getTimezone();

        $rule = $this->getRecurrenceRule();
        $rule = new \Recurr\Rule("{$rule}", $startDate, null, $timezone);

        // Fix for months with less than 31 days
        $transformerConfig = new \Recurr\Transformer\ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();

        $transformer = new \Recurr\Transformer\ArrayTransformer();
        $transformer->setConfig($transformerConfig);
        $dates = $transformer->transform($rule);

        if (count($dates) < 1) {
            return false;
        }

        return $dates;
    }

    /**
     * @return null
     */
    public function getNextSendDate()
    {
        // expenses don't have an is_public flag
        if ($this->is_recurring && ! $this->is_public) {
            return null;
        }

        if ($this->start_date && ! $this->last_sent_date) {
            $startDate = $this->getOriginal('start_date') . ' ' . $this->account->recurring_hour . ':00:00';

            return $this->account->getDateTime($startDate);
        }

        if (! $schedule = $this->getSchedule()) {
            return null;
        }

        if (count($schedule) < 2) {
            return null;
        }

        return $schedule[1]->getStart();
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
            $rule .= 'UNTIL=' . $this->getOriginal('end_date') . ' 24:00:00';
        }

        return $rule;
    }
}
