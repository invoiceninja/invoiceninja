<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;
use Carbon\Carbon;

class UpdateReminder extends AbstractService
{
    public $invoice;

    public $settings;

    public function __construct(Invoice $invoice, $settings = null)
    {
        $this->invoice = $invoice;
        $this->settings = $settings;
    }

    public function run()
    {
        if (! $this->settings) {
            $this->settings = $this->invoice->client->getMergedSettings();
        }

        if (! $this->invoice->isPayable() || $this->invoice->status_id == Invoice::STATUS_DRAFT) {
            $this->invoice->next_send_date = null;
            $this->invoice->saveQuietly();

            return $this->invoice; //exit early
        }

        if ($this->invoice->next_send_date) {
            $this->invoice->next_send_date = null;
        }

        $offset = $this->invoice->client->timezone_offset();

        $date_collection = collect();

        if (is_null($this->invoice->reminder1_sent) &&
            $this->settings->schedule_reminder1 == 'after_invoice_date') {
            $reminder_date = Carbon::parse($this->invoice->date)->startOfDay()->addDays($this->settings->num_days_reminder1)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder1_sent) &&
            $this->invoice->due_date &&
            $this->settings->schedule_reminder1 == 'before_due_date') {
            $reminder_date = Carbon::parse($this->invoice->due_date)->startOfDay()->subDays($this->settings->num_days_reminder1)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder1_sent) &&
            $this->invoice->due_date &&
            $this->settings->schedule_reminder1 == 'after_due_date') {
            $reminder_date = Carbon::parse($this->invoice->due_date)->startOfDay()->addDays($this->settings->num_days_reminder1)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder2_sent) &&
            $this->settings->schedule_reminder2 == 'after_invoice_date') {
            $reminder_date = Carbon::parse($this->invoice->date)->startOfDay()->addDays($this->settings->num_days_reminder2)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder2_sent) &&
            $this->invoice->due_date &&
            $this->settings->schedule_reminder2 == 'before_due_date') {
            $reminder_date = Carbon::parse($this->invoice->due_date)->startOfDay()->subDays($this->settings->num_days_reminder2)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder2_sent) &&
            $this->invoice->due_date &&
            $this->settings->schedule_reminder2 == 'after_due_date') {
            $reminder_date = Carbon::parse($this->invoice->due_date)->startOfDay()->addDays($this->settings->num_days_reminder2)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder3_sent) &&
            $this->settings->schedule_reminder3 == 'after_invoice_date') {
            $reminder_date = Carbon::parse($this->invoice->date)->startOfDay()->addDays($this->settings->num_days_reminder3)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder3_sent) &&
            $this->invoice->due_date &&
            $this->settings->schedule_reminder3 == 'before_due_date') {
            $reminder_date = Carbon::parse($this->invoice->due_date)->startOfDay()->subDays($this->settings->num_days_reminder3)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->invoice->reminder3_sent) &&
            $this->invoice->due_date &&
            $this->settings->schedule_reminder3 == 'after_due_date') {
            $reminder_date = Carbon::parse($this->invoice->due_date)->startOfDay()->addDays($this->settings->num_days_reminder3)->addSeconds($offset);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                $date_collection->push($reminder_date);
            }
        }

        if ($this->invoice->last_sent_date &&
            $this->settings->enable_reminder_endless) {
            $reminder_date = $this->addTimeInterval($this->invoice->last_sent_date, (int) $this->settings->endless_reminder_frequency_id);

            if ($reminder_date) {
                $reminder_date->addSeconds($offset);

                if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date))) {
                    $date_collection->push($reminder_date);
                }
            }
        }

        if ($date_collection->count() >= 1 && $date_collection->sort()->first()->gte(now())) {
            $this->invoice->next_send_date = $date_collection->sort()->first();
        } else {
            $this->invoice->next_send_date = null;
        }

        return $this->invoice;
    }

    private function testReminderValid($reminder_number, $reminder_schedule) :bool
    {
        $reminder_sent = "reminder{$reminder_number}_sent";
        $schedule_reminder = "schedule_reminder{$reminder_number}";
        $enable_reminder = "enable_reminder{$reminder_number}";
        $late_fee_amount = "late_fee_amount{$reminder_number}";
        $late_fee_percent = "late_fee_percent{$reminder_number}";

        return is_null($this->invoice->{$reminder_sent}) &&
            $this->settings->{$schedule_reminder} == $reminder_schedule &&
            ($this->settings->{$enable_reminder} || $late_fee_percent > 0 || $late_fee_amount > 0);
    }

    private function addTimeInterval($date, $endless_reminder_frequency_id) :?Carbon
    {
        if (! $date) {
            return null;
        }

        switch ($endless_reminder_frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return Carbon::parse($date)->addDay()->startOfDay();
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
