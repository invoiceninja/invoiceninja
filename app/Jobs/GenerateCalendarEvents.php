<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Task;

class GenerateCalendarEvents extends Job
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = [];

        $invoices = Invoice::scope()
            ->where('is_recurring', '=', false)
            ->get();
        foreach ($invoices as $invoice) {
            $data[] = $invoice->present()->calendarEvent;
        }

        $tasks = Task::scope()
            ->get();
        foreach ($tasks as $task) {
            $data[] = $task->present()->calendarEvent;
        }

        $payments = Payment::scope()
            ->get();
        foreach ($payments as $payment) {
            $data[] = $payment->present()->calendarEvent;
        }

        $expenses = Expense::scope()
            ->get();
        foreach ($expenses as $expense) {
            $data[] = $expense->present()->calendarEvent;
        }

        return $data;
    }
}
