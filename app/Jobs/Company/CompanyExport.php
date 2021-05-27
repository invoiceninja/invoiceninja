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

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Mail\DownloadBackup;
use App\Mail\DownloadInvoices;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Models\User;
use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class CompanyExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $company;

    private $export_format;

    private $export_data = [];

    public $user;

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param User $user
     * @param string $custom_token_name
     */
    public function __construct(Company $company, User $user, $export_format = 'json')
    {
        $this->company = $company;
        $this->user = $user;
        $this->export_format = $export_format;
    }

    /**
     * Execute the job.
     *
     * @return CompanyToken|null
     */
    public function handle()
    {

        MultiDB::setDb($this->company->db);

        $this->company = Company::where('company_key', $this->company->company_key)->first();

        set_time_limit(0);

        $this->export_data['app_version'] = config('ninja.app_version');

        $this->export_data['activities'] = $this->company->all_activities->map(function ($activity){

            $activity = $this->transformArrayOfKeys($activity, [
                'user_id',
                'company_id',
                'client_id',
                'client_contact_id',
                'account_id',
                'project_id',
                'vendor_id',
                'payment_id',
                'invoice_id',
                'credit_id',
                'invitation_id',
                'task_id',
                'expense_id',
                'token_id',
                'quote_id',
                'subscription_id',
                'recurring_invoice_id'
            ]);

            return $activity;

        })->makeHidden(['id'])->all();

        $this->export_data['backups'] = $this->company->all_activities()->with('backup')->cursor()->map(function ($activity){

            $backup = $activity->backup;

            if(!$backup)
                return;

            $backup->activity_id = $this->encodePrimaryKey($backup->activity_id);

            return $backup;

        })->all();

        $this->export_data['users'] = $this->company->users()->withTrashed()->cursor()->map(function ($user){

            $user->account_id = $this->encodePrimaryKey($user->account_id);
            // $user->id = $this->encodePrimaryKey($user->id);

            return $user;

        })->all();


        $this->export_data['client_contacts'] = $this->company->client_contacts->map(function ($client_contact){

            $client_contact = $this->transformArrayOfKeys($client_contact, ['id', 'company_id', 'user_id',' client_id']);

            return $client_contact;

        })->all();


        $this->export_data['client_gateway_tokens'] = $this->company->client_gateway_tokens->map(function ($client_gateway_token){

            $client_gateway_token = $this->transformArrayOfKeys($client_gateway_token, ['id', 'company_id', 'client_id']);

            return $client_gateway_token;

        })->all();


        $this->export_data['clients'] = $this->company->clients->map(function ($client){

            $client = $this->transformArrayOfKeys($client, ['id', 'company_id', 'user_id',' assigned_user_id', 'group_settings_id']);

            return $client;

        })->all();

        $this->export_data['company'] = $this->company->toArray();

        $this->export_data['company_gateways'] = $this->company->company_gateways->map(function ($company_gateway){

            $company_gateway = $this->transformArrayOfKeys($company_gateway, ['company_id', 'user_id']);
            $company_gateway->config = decrypt($company_gateway->config);
            
            return $company_gateway;

        })->all();

        $this->export_data['company_tokens'] = $this->company->tokens->map(function ($token){

            $token = $this->transformArrayOfKeys($token, ['company_id', 'account_id', 'user_id']);

            return $token;

        })->all();

        $this->export_data['company_ledger'] = $this->company->ledger->map(function ($ledger){

            $ledger = $this->transformArrayOfKeys($ledger, ['activity_id', 'client_id', 'company_id', 'account_id', 'user_id','company_ledgerable_id']);

            return $ledger;

        })->all();

        $this->export_data['company_users'] = $this->company->company_users->map(function ($company_user){

            $company_user = $this->transformArrayOfKeys($company_user, ['company_id', 'account_id', 'user_id']);

            return $company_user;

        })->all();

        $this->export_data['credits'] = $this->company->credits->map(function ($credit){

            $credit = $this->transformBasicEntities($credit);
            $credit = $this->transformArrayOfKeys($credit, ['recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id','invoice_id']);

            return $credit;

        })->all();


        $this->export_data['credit_invitations'] = CreditInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($credit){

            $credit = $this->transformArrayOfKeys($credit, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $credit;

        })->all();

        $this->export_data['designs'] = $this->company->user_designs->makeHidden(['id'])->all();

        $this->export_data['documents'] = $this->company->documents->map(function ($document){

            $document = $this->transformArrayOfKeys($document, ['user_id', 'assigned_user_id', 'company_id', 'project_id', 'vendor_id']);

            return $document;

        })->all();

        $this->export_data['expense_categories'] = $this->company->expenses->map(function ($expense_category){

            $expense_category = $this->transformArrayOfKeys($expense_category, ['user_id', 'company_id']);
            
            return $expense_category;

        })->all();


        $this->export_data['expenses'] = $this->company->expenses->map(function ($expense){

            $expense = $this->transformBasicEntities($expense);
            $expense = $this->transformArrayOfKeys($expense, ['vendor_id', 'invoice_id', 'client_id', 'category_id', 'recurring_expense_id','project_id']);

            return $expense;

        })->all();

        $this->export_data['group_settings'] = $this->company->group_settings->map(function ($gs){

            $gs = $this->transformArrayOfKeys($gs, ['user_id', 'company_id']);

            return $gs;

        })->all();


        $this->export_data['invoices'] = $this->company->invoices->map(function ($invoice){

            $invoice = $this->transformBasicEntities($invoice);
            $invoice = $this->transformArrayOfKeys($invoice, ['recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);

            return $invoice;

        })->all();


        $this->export_data['invoice_invitations'] = InvoiceInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($invoice){

            $invoice = $this->transformArrayOfKeys($invoice, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $invoice;

        })->all();

        $this->export_data['payment_terms'] = $this->company->user_payment_terms->map(function ($term){

            $term = $this->transformArrayOfKeys($term, ['user_id', 'company_id']);

            return $term;

        })->makeHidden(['id'])->all();

        $this->export_data['paymentables'] = $this->company->payments()->with('paymentables')->cursor()->map(function ($paymentable){

            $paymentable = $this->transformArrayOfKeys($paymentable, ['payment_id','paymentable_id']);

            return $paymentable;

        })->all();

        $this->export_data['payments'] = $this->company->payments->map(function ($payment){

            $payment = $this->transformBasicEntities($payment);
            $payment = $this->transformArrayOfKeys($payment, ['client_id','project_id', 'vendor_id', 'client_contact_id', 'invitation_id', 'company_gateway_id']);

            return $payment;

        })->all();


        $this->export_data['projects'] = $this->company->projects->map(function ($project){

            $project = $this->transformBasicEntities($project);
            $project = $this->transformArrayOfKeys($project, ['client_id']);

            return $project;

        })->all();

        $this->export_data['quotes'] = $this->company->quotes->map(function ($quote){

            $quote = $this->transformBasicEntities($quote);
            $quote = $this->transformArrayOfKeys($quote, ['invoice_id','recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);

            return $quote;

        })->all();


        $this->export_data['quote_invitations'] = QuoteInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($quote){

            $quote = $this->transformArrayOfKeys($quote, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $quote;

        })->all();


        $this->export_data['recurring_invoices'] = $this->company->recurring_invoices->map(function ($ri){

            $ri = $this->transformBasicEntities($ri);
            $ri = $this->transformArrayOfKeys($ri, ['client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);
            return $ri;

        })->all();


        $this->export_data['recurring_invoice_invitations'] = RecurringInvoiceInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($ri){

            $ri = $this->transformArrayOfKeys($ri, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $ri;

        })->all();

        $this->export_data['subscriptions'] = $this->company->subscriptions->map(function ($subscription){

            $subscription = $this->transformBasicEntities($subscription);
            $subscription->group_id = $this->encodePrimaryKey($subscription->group_id);

            return $subscription;

        })->all();


        $this->export_data['system_logs'] = $this->company->system_logs->map(function ($log){

            $log->client_id = $this->encodePrimaryKey($log->client_id);
            $log->company_id = $this->encodePrimaryKey($log->company_id);

            return $log;

        })->makeHidden(['id'])->all();

        $this->export_data['tasks'] = $this->company->tasks->map(function ($task){

            $task = $this->transformBasicEntities($task);
            $task = $this->transformArrayOfKeys($task, ['client_id', 'invoice_id', 'project_id', 'status_id']);

            return $task;

        })->all();

        $this->export_data['task_statuses'] = $this->company->task_statuses->map(function ($status){

            $status->id = $this->encodePrimaryKey($status->id);
            $status->user_id = $this->encodePrimaryKey($status->user_id);
            $status->company_id = $this->encodePrimaryKey($status->company_id);

            return $status;

        })->all();

        $this->export_data['tax_rates'] = $this->company->tax_rates->map(function ($rate){
            
            $rate->company_id = $this->encodePrimaryKey($rate->company_id);
            $rate->user_id = $this->encodePrimaryKey($rate->user_id);

            return $rate;

        })->makeHidden(['id'])->all();

        $this->export_data['vendors'] = $this->company->vendors->map(function ($vendor){

            return $this->transformBasicEntities($vendor);

        })->all();


        $this->export_data['vendor_contacts'] = VendorContact::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($vendor){

            $vendor = $this->transformBasicEntities($vendor);
            $vendor->vendor_id = $this->encodePrimaryKey($vendor->vendor_id);

            return $vendor;

        })->all();

        $this->export_data['webhooks'] = $this->company->webhooks->map(function ($hook){

            $hook->user_id = $this->encodePrimaryKey($hook->user_id);
            $hook->company_id = $this->encodePrimaryKey($hook->company_id);

            return $hook;

        })->makeHidden(['id'])->all();

        //write to tmp and email to owner();

        $this->zipAndSend();  

        return true;      
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

    private function zipAndSend()
    {

        $file_name = date('Y-m-d').'_'.str_replace(' ', '_', $this->company->present()->name() . '_' . $this->company->company_key .'.zip');

        $zip_path = public_path('storage/backups/'.$file_name);
        $zip = new \ZipArchive();

        if ($zip->open($zip_path, \ZipArchive::CREATE)!==TRUE) {
            nlog("cannot open {$zip_path}");
        }

        $zip->addFromString("backup.json", json_encode($this->export_data));
        $zip->close();

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new DownloadBackup(Storage::disk(config('filesystems.default'))->url('backups/'.$file_name), $this->company);
        $nmo->to_user = $this->user;
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        
        NinjaMailerJob::dispatch($nmo);

        UnlinkFile::dispatch(config('filesystems.default'), 'backups/'.$file_name)->delay(now()->addHours(1));
    }

}
