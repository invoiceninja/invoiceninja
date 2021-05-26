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

namespace App\Services\Invoice;

use App\Models\Invoice;
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

        if (! $this->invoice->isPayable()) {
            $this->invoice->next_send_date = null;
            $this->invoice->save();

            return $this->invoice; //exit early
        }

        $date_collection = collect();

        if (is_null($this->invoice->reminder1_sent) &&
            $this->settings->schedule_reminder1 == 'after_invoice_date' &&
            $this->settings->num_days_reminder1 > 0) {
            $reminder_date = Carbon::parse($this->invoice->date)->addDays($this->settings->num_days_reminder1);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d'));
        }

        if (is_null($this->invoice->reminder1_sent) &&
            $this->settings->schedule_reminder1 == 'before_due_date' &&
            $this->settings->num_days_reminder1 > 0) {
            $reminder_date = Carbon::parse($this->invoice->due_date)->subDays($this->settings->num_days_reminder1);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d')); 
        }

        if (is_null($this->invoice->reminder1_sent) &&
            $this->settings->schedule_reminder1 == 'after_due_date' &&
            $this->settings->num_days_reminder1 > 0) {
            $reminder_date = Carbon::parse($this->invoice->due_date)->addDays($this->settings->num_days_reminder1);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d'));  
        }

        if (is_null($this->invoice->reminder2_sent) &&
            $this->settings->schedule_reminder2 == 'after_invoice_date' &&
            $this->settings->num_days_reminder2 > 0) {
            $reminder_date = Carbon::parse($this->invoice->date)->addDays($this->settings->num_days_reminder2);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d')); 
        }

        if (is_null($this->invoice->reminder2_sent) &&
            $this->settings->schedule_reminder2 == 'before_due_date' &&
            $this->settings->num_days_reminder2 > 0) {
            $reminder_date = Carbon::parse($this->invoice->due_date)->subDays($this->settings->num_days_reminder2);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d')); 
        }

        if (is_null($this->invoice->reminder2_sent) &&
            $this->settings->schedule_reminder2 == 'after_due_date' &&
            $this->settings->num_days_reminder2 > 0) {
            $reminder_date = Carbon::parse($this->invoice->due_date)->addDays($this->settings->num_days_reminder2);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d')); 
        }

        if (is_null($this->invoice->reminder3_sent) &&
            $this->settings->schedule_reminder3 == 'after_invoice_date' &&
            $this->settings->num_days_reminder3 > 0) {
            $reminder_date = Carbon::parse($this->invoice->date)->addDays($this->settings->num_days_reminder3);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d')); 
        }

        if (is_null($this->invoice->reminder3_sent) &&
            $this->settings->schedule_reminder3 == 'before_due_date' &&
            $this->settings->num_days_reminder3 > 0) {
            $reminder_date = Carbon::parse($this->invoice->due_date)->subDays($this->settings->num_days_reminder3);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d')); 
        }

        if (is_null($this->invoice->reminder3_sent) &&
            $this->settings->schedule_reminder3 == 'after_due_date' &&
            $this->settings->num_days_reminder3 > 0) {
            $reminder_date = Carbon::parse($this->invoice->due_date)->addDays($this->settings->num_days_reminder3);

            if ($reminder_date->gt(Carbon::parse($this->invoice->next_send_date)));
                $date_collection->push($reminder_date->format('Y-m-d')); 
        }

        $this->invoice->next_send_date = $date_collection->sort()->first();

        return $this->invoice;
    }
}