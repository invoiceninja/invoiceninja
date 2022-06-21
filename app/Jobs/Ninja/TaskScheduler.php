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

namespace App\Jobs\Ninja;

use App\Jobs\Report\SendToAdmin;
use App\Libraries\MultiDB;
use App\Models\Scheduler;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TaskScheduler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            Scheduler::with('company')
                ->where('paused', false)
                ->where('is_deleted', false)
                ->where('scheduled_run', '<', now())
                ->cursor()
                ->each(function ($scheduler) {
                    $this->doJob($scheduler);
                });
        }
    }

    private function doJob(Scheduler $scheduler)
    {
        nlog("Doing job {$scheduler->action_name}");

        $company = $scheduler->company;

        $parameters = $scheduler->parameters;

        switch ($scheduler->action_name) {
            case Scheduler::CREATE_CLIENT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'contacts.csv');
                break;
            case Scheduler::CREATE_CLIENT_CONTACT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'clients.csv');
                break;
            case Scheduler::CREATE_CREDIT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'credits.csv');
                break;
            case Scheduler::CREATE_DOCUMENT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'documents.csv');
                break;
            case Scheduler::CREATE_EXPENSE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'expense.csv');
                break;
            case Scheduler::CREATE_INVOICE_ITEM_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'invoice_items.csv');
                break;
            case Scheduler::CREATE_INVOICE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'invoices.csv');
                break;
            case Scheduler::CREATE_PAYMENT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'payments.csv');
                break;
            case Scheduler::CREATE_PRODUCT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'products.csv');
                break;
            case Scheduler::CREATE_PROFIT_AND_LOSS_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'profit_and_loss.csv');
                break;
            case Scheduler::CREATE_QUOTE_ITEM_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'quote_items.csv');
                break;
            case Scheduler::CREATE_QUOTE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'quotes.csv');
                break;
            case Scheduler::CREATE_RECURRING_INVOICE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'recurring_invoices.csv');
                break;
            case Scheduler::CREATE_TASK_REPORT:
                SendToAdmin::dispatch($company, $parameters, $scheduler->action_class, 'tasks.csv');
                break;

        }

        $scheduler->scheduled_run = $scheduler->nextScheduledDate();
        $scheduler->save();
    }
}
