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

namespace App\Utils\Traits;

use App\Models\RecurringInvoice;
use Illuminate\Support\Carbon;

/**
 * Class MakesReminders.
 */
trait MakesReminders
{
    public function setReminder($settings = null)
    {
        if (! $settings) {
            $settings = $this->client->getMergedSettings();
        }

        if (! $this->isPayable()) {
            $this->next_send_date = null;
            $this->save();

            return; //exit early
        }

        $date_collection = collect();

        if ($settings->schedule_reminder1 == 'after_invoice_date' &&
            $settings->num_days_reminder1 > 0) {
            $reminder_date = Carbon::parse($this->date)->addDays($settings->num_days_reminder1);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder1 == 'before_due_date' &&
            $settings->num_days_reminder1 > 0) {
            $reminder_date = Carbon::parse($this->due_date)->subDays($settings->num_days_reminder1);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder1 == 'after_due_date' &&
            $settings->num_days_reminder1 > 0) {
            $reminder_date = Carbon::parse($this->due_date)->addDays($settings->num_days_reminder1);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder2 == 'after_invoice_date' &&
            $settings->num_days_reminder2 > 0) {
            $reminder_date = Carbon::parse($this->date)->addDays($settings->num_days_reminder2);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder2 == 'before_due_date' &&
            $settings->num_days_reminder2 > 0) {
            $reminder_date = Carbon::parse($this->due_date)->subDays($settings->num_days_reminder2);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder2 == 'after_due_date' &&
            $settings->num_days_reminder2 > 0) {
            $reminder_date = Carbon::parse($this->due_date)->addDays($settings->num_days_reminder2);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder3 == 'after_invoice_date' &&
            $settings->num_days_reminder3 > 0) {
            $reminder_date = Carbon::parse($this->date)->addDays($settings->num_days_reminder3);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder3 == 'before_due_date' &&
            $settings->num_days_reminder3 > 0) {
            $reminder_date = Carbon::parse($this->due_date)->subDays($settings->num_days_reminder3);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if ($settings->schedule_reminder3 == 'after_due_date' &&
            $settings->num_days_reminder3 > 0) {
            $reminder_date = Carbon::parse($this->due_date)->addDays($settings->num_days_reminder3);

            if ($reminder_date->gt(Carbon::parse($this->next_send_date)));
            $date_collection->push($reminder_date->format('Y-m-d'));
        }

        $this->next_send_date = $date_collection->sort()->first();

        $this->save();
    }

    public function inReminderWindow($schedule_reminder, $num_days_reminder)
    {
        switch ($schedule_reminder) {
            case 'after_invoice_date':
                return Carbon::parse($this->date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'before_due_date':
                return Carbon::parse($this->due_date)->subDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'after_due_date':
                return Carbon::parse($this->due_date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            default:
                return null;
                break;
        }
    }

    public function calculateTemplate(string $entity_string): string
    {
        //if invoice is currently a draft, or being marked as sent, this will be the initial email
        $client = $this->client;

        if ($entity_string != 'invoice') {
            return $entity_string;
        }

        //if the invoice
        if ($this->inReminderWindow(
            $client->getSetting('schedule_reminder1'),
            $client->getSetting('num_days_reminder1')
        )) {
            return 'reminder1';
        } elseif ($this->inReminderWindow(
            $client->getSetting('schedule_reminder2'),
            $client->getSetting('num_days_reminder2')
        )) {
            return 'reminder2';
        } elseif ($this->inReminderWindow(
            $client->getSetting('schedule_reminder3'),
            $client->getSetting('num_days_reminder3')
        )) {
            return 'reminder3';
        } elseif ($this->checkEndlessReminder(
            $this->last_sent_date,
            $client->getSetting('endless_reminder_frequency_id')
        )) {
            return 'endless_reminder';
        } else {
            return $entity_string;
        }

        //also implement endless reminders here
    }

    private function checkEndlessReminder($last_sent_date, $endless_reminder_frequency_id) :bool
    {
        if (Carbon::now()->startOfDay()->eq($this->addTimeInterval($last_sent_date, $endless_reminder_frequency_id))) {
            return true;
        }

        return false;
    }

    private function addTimeInterval($date, $endless_reminder_frequency_id) :?Carbon
    {
        if (!$date) {
            return null;
        }
        

        switch ($endless_reminder_frequency_id) {
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return Carbon::parse($date)->addWeek()->startOfDay();
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($date)->addWeeks(2)->startOfDay();
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($date)->addWeeks(4)->startOfDay();
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return Carbon::parse($date)->addMonthNoOverflow()->startOfDay();
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($date)->addMonthsNoOverflow(2)->startOfDay();
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($date)->addMonthsNoOverflow(3)->startOfDay();
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($date)->addMonthsNoOverflow(4)->startOfDay();
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($date)->addMonthsNoOverflow(6)->startOfDay();
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return Carbon::parse($date)->addYear()->startOfDay();
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return Carbon::parse($date)->addYears(2)->startOfDay();
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return Carbon::parse($date)->addYears(3)->startOfDay();
            default:
                return null;
        }
    }

}


