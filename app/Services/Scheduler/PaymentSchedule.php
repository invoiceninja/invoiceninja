<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Scheduler;

use App\Models\Invoice;
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;

class PaymentSchedule
{
    use MakesHash;

    public function __construct(public Scheduler $scheduler) {}

    /**
     * Populates the invoice partial / due dates at the time the payment
     * schedule is created. The first instalment becomes the partial amount
     * and partial_due_date, whilst the due_date is set to the final instalment.
     */
    public function seed(): void
    {
        if (!isset($this->scheduler->parameters['invoice_id'])) {
            return;
        }

        $invoice = Invoice::find($this->decodePrimaryKey($this->scheduler->parameters['invoice_id']));

        if (!$invoice || $invoice->is_deleted) {
            return;
        }

        $schedule = collect($this->scheduler->parameters['schedule'] ?? [])
            ->sortBy('date')
            ->values()
            ->all();

        if (count($schedule) === 0) {
            return;
        }

        $first = $schedule[0];
        $last = end($schedule);
        $offset = $invoice->company->timezone_offset();

        // A single instalment behaves as the LAST instalment: the entire balance is
        // due on that date. The scheduler is kept (so it still blocks a duplicate
        // schedule for this invoice) and run() finalises + cleans it up on that date.
        if (count($schedule) === 1) {
            $invoice->partial = null;
            $invoice->partial_due_date = null;
            $invoice->due_date = $first['date'];
            $invoice->save();

            $this->scheduler->next_run_client = $first['date'];
            $this->scheduler->next_run = Carbon::parse($first['date'])->addSeconds($offset);
            $this->scheduler->save();

            return;
        }

        $amount = $first['is_amount'] ? $first['amount'] : round(($first['amount'] / 100) * $invoice->amount, 2);

        $amount = min($amount, $invoice->amount);

        // A draft invoice has not had its balance populated yet (balance == 0), so only clamp
        // to balance once it reflects a real outstanding amount - otherwise the partial is
        // wrongly zeroed when seeding a schedule from a draft.
        if ($invoice->balance > 0 && $amount > $invoice->balance) {
            $amount = $invoice->balance;
        }

        $invoice->partial = $amount;
        $invoice->partial_due_date = $first['date'];
        $invoice->due_date = $last['date'];

        $invoice->save();

        // The first instalment is assigned here, so advance the scheduler to the
        // second instalment - run() owns instalments 2..n and never re-applies the first.
        $this->scheduler->next_run_client = $schedule[1]['date'];
        $this->scheduler->next_run = Carbon::parse($schedule[1]['date'])->addSeconds($offset);
        $this->scheduler->save();
    }

    public function run()
    {
        //Handle if the invoice_id has been deleted
        if (!isset($this->scheduler->parameters['invoice_id'])) {
            $this->scheduler->forceDelete();
            return;
        }

        $invoice = Invoice::find($this->decodePrimaryKey($this->scheduler->parameters['invoice_id']));

        // Needs to be draft, partial or sent AND not deleted
        if (!$invoice || !in_array($invoice->status_id, [Invoice::STATUS_DRAFT, Invoice::STATUS_PARTIAL, Invoice::STATUS_SENT]) || $invoice->is_deleted) {
            $this->scheduler->forceDelete();
            return;
        }

        $invoice = $invoice->service()->markSent()->save();

        $offset = $invoice->company->timezone_offset();
        $schedule = collect($this->scheduler->parameters['schedule'])->sortBy('date')->values()->all();
        $schedule_index = 0;
        $next_schedule = false;

        foreach ($schedule as $key => $item) {
            if (now()->subSeconds($offset)->startOfDay()->eq(Carbon::parse($item['date'])->startOfDay())) {
                $next_schedule = $item;
                $schedule_index = $key;
            }
        }

        if (!$next_schedule) {
            $this->scheduler->forceDelete();
            return;
        }

        if ($schedule_index === count($schedule) - 1) {
            // Last instalment: the entire remaining balance becomes due today (localized).
            $invoice->partial = null;
            $invoice->partial_due_date = null;
            $invoice->due_date = now()->subSeconds($offset)->format('Y-m-d');
        } else {
            $amount = $next_schedule['is_amount'] ? $next_schedule['amount'] : round(($next_schedule['amount'] / 100) * $invoice->amount, 2);

            $amount = min($amount, $invoice->amount);

            if ($amount > $invoice->balance) {
                $amount = $invoice->balance;
            }

            $invoice->partial += $amount;
            $invoice->partial_due_date = $next_schedule['date'];
            $invoice->due_date = $schedule[count($schedule) - 1]['date'];
        }

        $invoice->save();

        if ($this->scheduler->parameters['auto_bill']) {

            try {
                $invoice->service()->autoBill();
            } catch (\Throwable $e) {
                nlog("Error auto-billing invoice {$invoice->id}: {$e->getMessage()}");
            }
        } else {
            $invoice->service()->sendEmail();
        }

        $total_schedules = count($schedule);

        if (isset($schedule[$schedule_index + 1])) {
            $next_run = $schedule[$schedule_index + 1]['date'];
            $this->scheduler->next_run_client = $next_run;
            $this->scheduler->next_run = Carbon::parse($next_run)->addSeconds($offset);
            $this->scheduler->save();
        } else {
            $this->scheduler->forceDelete();
        }
    }
}
