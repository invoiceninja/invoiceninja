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

namespace App\Jobs\Company;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompanyExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    protected $company;

    private $export_format;

    private $export_data = [];

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param User $user
     * @param string $custom_token_name
     */
    public function __construct(Company $company, $export_format = 'json')
    {
        $this->company = $company;
        $this->export_format = $export_format;
    }

    /**
     * Execute the job.
     *
     * @return CompanyToken|null
     */
    public function handle() : void
    {

        MultiDB::setDb($this->company->db);

        set_time_limit(0);

        $this->export_data['company'] = $this->company->makeHidden(['id','account_id'])->toArray();

        $this->export_data['design'] = $this->company->user_designs->makeHidden(['id'])->toArray();

        $this->export_data['payment_terms'] = $this->company->user_payment_terms->map(function ($term){

            $term = $this->transformArrayOfKeys($term, ['user_id', 'company_id']);

            return $term;

        })->makeHidden(['id'])->toArray();

        $this->export_data['projects'] = $this->company->projects->map(function ($project){

            $project = $this->transformBasicEntities($project);
            $project = $this->transformArrayOfKeys($project, ['client_id']);

            return $project;

        })->toArray();

        $this->export_data['quotes'] = $this->company->quotes->map(function ($quote){

            $quote = $this->transformBasicEntities($quote);
            $quote = $this->transformArrayOfKeys($quote, ['invoice_id','recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);

            return $quote;

        })->toArray();


        $this->export_data['quote_invitations'] = QuoteInvitation::where('company_id', $this->company_id)->withTrashed()->cursor()->map(function ($quote){

            $quote = $this->transformArrayOfKeys($quote, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $quote;

        })->toArray();


        $this->export_data['recurring_invoices'] = $this->company->recurring_invoices->map(function ($ri){

            $ri = $this->transformBasicEntities($ri);
            $ri = $this->transformArrayOfKeys($ri, [['client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);
            return $ri;

        })->toArray();


        $this->export_data['recurring_invoice_invitations'] = RecurringInvoice::where('company_id', $this->company_id)->withTrashed()->cursor()->map(function ($ri){

            $ri = $this->transformArrayOfKeys($ri, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $ri;

        })->toArray();

        $this->export_data['subscriptions'] = $this->company->subscriptions->map(function ($subscription){

            $subscription = $this->transformBasicEntities($subscription);
            $subscription->group_id = $this->encodePrimaryKey($group_id);

            return $subscription;

        })->makeHidden([])->toArray();


        $this->export_data['system_logs'] = $this->company->system_logs->map(function ($log){

            $log->client_id = $this->encodePrimaryKey($log->client_id);
            $log->company_id = $this->encodePrimaryKey($log->company_id);

            return $log;

        })->makeHidden(['id'])->toArray();

        $this->export_data['tasks'] = $this->company->tasks->map(function ($task){

            $task = $this->transformBasicEntities($task);
            $task = $this->transformArrayOfKeys(['client_id', 'invoice_id', 'project_id', 'status_id']);

            return $task

        })->makeHidden([])->toArray();

        $this->export_data['task_statuses'] = $this->company->task_statuses->map(function ($status){

            $status->id = $this->encodePrimaryKey($status->id);
            $status->user_id = $this->encodePrimaryKey($status->user_id);
            $status->company_id = $this->encodePrimaryKey($status->company_id);

            return $status;

        })->makeHidden([])->toArray();

        $this->export_data['tax_rates'] = $this->company->tax_rates->map(function ($rate){
            
            $rate->company_id = $this->encodePrimaryKey($rate->company_id);
            $rate->user_id = $this->encodePrimaryKey($rate->user_id);

            return $rate

        })->makeHidden(['id'])->toArray();

        $this->export_data['users'] = $this->company->users->map(function ($user){

            $user->account_id = $this->encodePrimaryKey($user->account_id);
            $user->id = $this->encodePrimaryKey($user->id);

            return $user;

        })->makeHidden(['ip'])->toArray();

        $this->export_data['vendors'] = $this->company->vendors->map(function ($vendor){

            return $this->transformBasicEntities($vendor);

        })->makeHidden([])->toArray();


        $this->export_data['vendor_contacts'] = $this->company->vendor->contacts->map(function ($vendor){

            $vendor = $this->transformBasicEntities($vendor);
            $vendor->vendor_id = $this->encodePrimaryKey($vendor->vendor_id);

            return $vendor;

        })->makeHidden([])->toArray();

        $this->export_data['webhooks'] = $this->company->webhooks->map(function ($hook){

            $hook->user_id = $this->encodePrimaryKey($hook->user_id);
            $hook->company_id = $this->encodePrimaryKey($hook->company_id);

            return $hook;

        })->makeHidden(['id'])->toArray();


    }

    private function transformBasicEntities($model)
    {

        return $this->transformArrayOfKeys($model, ['id', 'user_id', 'assigned_user_id', 'company_id']);

    }

    private function transformArrayOfKeys($model, $keys)
    {

        foreach($keys as $key){
            $model->{$key} = $this->encodePrimaryKey($model->{$key});
        }

        return $model;

    }

}
