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
use App\Models\Company;
use App\Models\ScheduledJob;
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
        foreach (MultiDB::$dbs as $db) 
        {

            MultiDB::setDB($db);

            $pending_schedulers = $this->fetchJobs();

            Scheduler::with('company','job')
                ->where('paused', false)
                ->where('is_deleted', false)
                ->where('scheduled_run', '<', now())
                ->cursor()
                ->each(function ($scheduler){

                    $this->doJob($scheduler);

                });

        }

    }

    private function doJob(Scheduler $scheduler)
    {
        nlog("Doing job {$scheduler->id}");
        
        $job = $scheduler->job;
        $company = $scheduler->company;

        if (!$job)
            return;
        
        $parameters = $job->parameters;


        switch ($job->action_name) {
            case ScheduledJob::CREATE_CLIENT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'contacts.csv');
                break;
            case ScheduledJob::CREATE_CLIENT_CONTACT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'clients.csv');
                break;
            case ScheduledJob::CREATE_CREDIT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'credits.csv');
                break;
            case ScheduledJob::CREATE_DOCUMENT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'documents.csv');
                break;
            case ScheduledJob::CREATE_EXPENSE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'expense.csv');
                break;
            case ScheduledJob::CREATE_INVOICE_ITEM_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'invoice_items.csv');
                break;
            case ScheduledJob::CREATE_INVOICE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'invoices.csv');
                break;
            case ScheduledJob::CREATE_PAYMENT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'payments.csv');
                break;
            case ScheduledJob::CREATE_PRODUCT_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'products.csv');
                break;
            case ScheduledJob::CREATE_PROFIT_AND_LOSS_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'profit_and_loss.csv');
                break;
            case ScheduledJob::CREATE_QUOTE_ITEM_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'quote_items.csv');
                break;
            case ScheduledJob::CREATE_QUOTE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'quotes.csv');
                break;
            case ScheduledJob::CREATE_RECURRING_INVOICE_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'recurring_invoices.csv');
                break;
            case ScheduledJob::CREATE_TASK_REPORT:
                SendToAdmin::dispatch($company, $parameters, $job->action_class, 'tasks.csv');
                break;

        }

        $scheduler->scheduled_run = $scheduler->nextScheduledDate();
        $scheduler->save();
    }


    private function fetchJobs()
    {
        return ;
    }

}
