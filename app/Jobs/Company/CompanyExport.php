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
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Models\User;
use App\Models\VendorContact;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

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

        // $this->export_data['backups'] = $this->company->all_activities()->with('backup')->cursor()->map(function ($activity){

        //     $backup = $activity->backup;

        //     if(!$backup)
        //         return;

        //     $backup->activity_id = $this->encodePrimaryKey($backup->activity_id);

        //     return $backup;

        // })->all();

        $this->export_data['users'] = $this->company->users()->withTrashed()->cursor()->map(function ($user){

            $user->account_id = $this->encodePrimaryKey($user->account_id);
            // $user->id = $this->encodePrimaryKey($user->id);

            return $user->makeVisible(['id']);

        })->all();


        $this->export_data['client_contacts'] = $this->company->client_contacts->map(function ($client_contact){

            $client_contact = $this->transformArrayOfKeys($client_contact, ['company_id', 'user_id', 'client_id']);

                        return $client_contact->makeVisible([
                            'password',
                            'remember_token',
                            'user_id',
                            'company_id',
                            'client_id',
                            'google_2fa_secret',
                            'id',
                            'oauth_provider_id',
                            'oauth_user_id',
                            'token',
                            'hashed_id',
                        ]);

        })->all();


        $this->export_data['client_gateway_tokens'] = $this->company->client_gateway_tokens->map(function ($client_gateway_token){

            $client_gateway_token = $this->transformArrayOfKeys($client_gateway_token, ['company_id', 'client_id', 'company_gateway_id']);

            return $client_gateway_token->makeVisible(['id']);

        })->all();


        $this->export_data['clients'] = $this->company->clients()->orderBy('number', 'DESC')->cursor()->map(function ($client){

            $client = $this->transformArrayOfKeys($client, ['company_id', 'user_id', 'assigned_user_id', 'group_settings_id']);

            return $client->makeVisible(['id','private_notes','user_id','company_id','last_login','hashed_id']);

        })->all();


        $this->export_data['company'] = $this->company->toArray();

        $this->export_data['company_gateways'] = $this->company->company_gateways()->withTrashed()->cursor()->map(function ($company_gateway){

            $company_gateway = $this->transformArrayOfKeys($company_gateway, ['company_id', 'user_id']);
            $company_gateway->config = decrypt($company_gateway->config);
            
            return $company_gateway->makeVisible(['id']);

        })->all();

        $this->export_data['company_tokens'] = $this->company->tokens->map(function ($token){

            $token = $this->transformArrayOfKeys($token, ['company_id', 'account_id', 'user_id']);

            return $token;

        })->all();

        $this->export_data['company_ledger'] = $this->company->ledger->map(function ($ledger){

            $ledger = $this->transformArrayOfKeys($ledger, ['activity_id', 'client_id', 'company_id', 'account_id', 'user_id','company_ledgerable_id']);

            return $ledger;

        })->all();

        $this->export_data['company_users'] = $this->company->company_users()->without(['user','account'])->cursor()->map(function ($company_user){

            $company_user = $this->transformArrayOfKeys($company_user, ['company_id', 'account_id', 'user_id']);

            return $company_user;

        })->all();

        $this->export_data['credits'] = $this->company->credits()->orderBy('number', 'DESC')->cursor()->map(function ($credit){

            $credit = $this->transformBasicEntities($credit);
            $credit = $this->transformArrayOfKeys($credit, ['recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id','invoice_id']);

            return $credit->makeVisible(['id']);

        })->all();


        $this->export_data['credit_invitations'] = CreditInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($credit){

            $credit = $this->transformArrayOfKeys($credit, ['company_id', 'user_id', 'client_contact_id', 'credit_id']);

            return $credit->makeVisible(['id']);

        })->all();

        $this->export_data['designs'] = $this->company->user_designs->makeHidden(['id'])->all();

        $this->export_data['documents'] = $this->company->all_documents->map(function ($document){

            $document = $this->transformArrayOfKeys($document, ['user_id', 'assigned_user_id', 'company_id', 'project_id', 'vendor_id','documentable_id']);
            $document->hashed_id = $this->encodePrimaryKey($document->id);

            return $document->makeVisible(['id']);

        })->all();

        $this->export_data['expense_categories'] = $this->company->expense_categories->map(function ($expense_category){

            $expense_category = $this->transformArrayOfKeys($expense_category, ['user_id', 'company_id']);
            
            return $expense_category->makeVisible(['id']);

        })->all();


        $this->export_data['expenses'] = $this->company->expenses()->orderBy('number', 'DESC')->cursor()->map(function ($expense){

            $expense = $this->transformBasicEntities($expense);
            $expense = $this->transformArrayOfKeys($expense, ['vendor_id', 'invoice_id', 'client_id', 'category_id', 'recurring_expense_id','project_id']);

            return $expense->makeVisible(['id']);

        })->all();

        $this->export_data['group_settings'] = $this->company->group_settings->map(function ($gs){

            $gs = $this->transformArrayOfKeys($gs, ['user_id', 'company_id']);

            return $gs->makeVisible(['id']);

        })->all();


        $this->export_data['invoices'] = $this->company->invoices()->orderBy('number', 'DESC')->cursor()->map(function ($invoice){

            $invoice = $this->transformBasicEntities($invoice);
            $invoice = $this->transformArrayOfKeys($invoice, ['recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id','project_id']);

            return $invoice->makeVisible(['id',
                                        'private_notes',
                                        'user_id',
                                        'client_id',
                                        'company_id',]);

        })->all();


        $this->export_data['invoice_invitations'] = InvoiceInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($invoice){

            $invoice = $this->transformArrayOfKeys($invoice, ['company_id', 'user_id', 'client_contact_id', 'invoice_id']);

            return $invoice->makeVisible(['id']);

        })->all();

        $this->export_data['payment_terms'] = $this->company->user_payment_terms->map(function ($term){

            $term = $this->transformArrayOfKeys($term, ['user_id', 'company_id']);

            return $term;

        })->makeHidden(['id'])->all();


        $this->export_data['payments'] = $this->company->payments()->orderBy('number', 'DESC')->cursor()->map(function ($payment){

            $payment = $this->transformBasicEntities($payment);
            $payment = $this->transformArrayOfKeys($payment, ['client_id','project_id', 'vendor_id', 'client_contact_id', 'invitation_id', 'company_gateway_id']);

            $payment->paymentables = $this->transformPaymentable($payment);

            return $payment->makeVisible(['id']);
            
        })->all();

        $this->export_data['products'] = $this->company->products->map(function ($product){

            $product = $this->transformBasicEntities($product);
            $product = $this->transformArrayOfKeys($product, ['vendor_id','project_id']);

            return $product->makeVisible(['id']);

        })->all();

        $this->export_data['projects'] = $this->company->projects()->orderBy('number', 'DESC')->cursor()->map(function ($project){

            $project = $this->transformBasicEntities($project);
            $project = $this->transformArrayOfKeys($project, ['client_id']);

            return $project->makeVisible(['id']);

        })->all();

        $this->export_data['quotes'] = $this->company->quotes()->orderBy('number', 'DESC')->cursor()->map(function ($quote){

            $quote = $this->transformBasicEntities($quote);
            $quote = $this->transformArrayOfKeys($quote, ['invoice_id','recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);

            return $quote->makeVisible(['id']);

        })->all();


        $this->export_data['quote_invitations'] = QuoteInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($quote){

            $quote = $this->transformArrayOfKeys($quote, ['company_id', 'user_id', 'client_contact_id', 'quote_id']);

            return $quote->makeVisible(['id']);

        })->all();

        $this->export_data['recurring_expenses'] = $this->company->recurring_expenses()->orderBy('number', 'DESC')->cursor()->map(function ($expense){

            $expense = $this->transformBasicEntities($expense);
            $expense = $this->transformArrayOfKeys($expense, ['vendor_id', 'invoice_id', 'client_id', 'category_id', 'project_id']);

            return $expense->makeVisible(['id']);

        })->all();

        $this->export_data['recurring_invoices'] = $this->company->recurring_invoices()->orderBy('number', 'DESC')->cursor()->map(function ($ri){

            $ri = $this->transformBasicEntities($ri);
            $ri = $this->transformArrayOfKeys($ri, ['client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);
            
            return $ri->makeVisible(['id']);

        })->all();


        $this->export_data['recurring_invoice_invitations'] = RecurringInvoiceInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($ri){

            $ri = $this->transformArrayOfKeys($ri, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $ri;

        })->all();

        $this->export_data['subscriptions'] = $this->company->subscriptions->map(function ($subscription){

            $subscription = $this->transformBasicEntities($subscription);
            $subscription->group_id = $this->encodePrimaryKey($subscription->group_id);

            return $subscription->makeVisible([ 'id',
                                                'user_id',
                                                'assigned_user_id',
                                                'company_id',
                                                'product_ids',
                                                'recurring_product_ids',
                                                'group_id']);

        })->all();


        $this->export_data['system_logs'] = $this->company->system_logs->map(function ($log){

            $log->client_id = $this->encodePrimaryKey($log->client_id);
            $log->company_id = $this->encodePrimaryKey($log->company_id);

            return $log;

        })->makeHidden(['id'])->all();

        $this->export_data['tasks'] = $this->company->tasks()->orderBy('number', 'DESC')->cursor()->map(function ($task){

            $task = $this->transformBasicEntities($task);
            $task = $this->transformArrayOfKeys($task, ['client_id', 'invoice_id', 'project_id', 'status_id']);

            return $task->makeVisible(['id']);

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

        $this->export_data['vendors'] = $this->company->vendors()->orderBy('number', 'DESC')->cursor()->map(function ($vendor){

            return $this->transformBasicEntities($vendor)->makeVisible(['id']);

        })->all();


        $this->export_data['vendor_contacts'] = VendorContact::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($vendor){

            $vendor = $this->transformBasicEntities($vendor);
            $vendor = $this->transformArrayOfKeys($vendor, ['vendor_id']);

            return $vendor->makeVisible(['id','user_id']);

        })->all();

        $this->export_data['webhooks'] = $this->company->webhooks->map(function ($hook){

            $hook->user_id = $this->encodePrimaryKey($hook->user_id);
            $hook->company_id = $this->encodePrimaryKey($hook->company_id);

            return $hook;

        })->makeHidden(['id'])->all();

        $this->export_data['purchase_orders'] = $this->company->purchase_orders()->orderBy('number', 'DESC')->cursor()->map(function ($purchase_order){

            $purchase_order = $this->transformBasicEntities($purchase_order);
            $purchase_order = $this->transformArrayOfKeys($purchase_order, ['expense_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id','project_id']);

            return $purchase_order->makeVisible(['id',
                                        'private_notes',
                                        'user_id',
                                        'client_id',
                                        'vendor_id',
                                        'company_id',]);

        })->all();


        $this->export_data['purchase_order_invitations'] = PurchaseOrderInvitation::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($purchase_order){

            $purchase_order = $this->transformArrayOfKeys($purchase_order, ['company_id', 'user_id', 'vendor_contact_id', 'purchase_order_id']);

            return $purchase_order->makeVisible(['id']);

        })->all();



        //write to tmp and email to owner();
        
        $this->zipAndSend();  

        return true;      
    }

    private function transformBasicEntities($model)
    {

        return $this->transformArrayOfKeys($model, ['user_id', 'assigned_user_id', 'company_id']);

    }

    private function transformArrayOfKeys($model, $keys)
    {

        foreach($keys as $key){
            $model->{$key} = $this->encodePrimaryKey($model->{$key});
        }

        return $model;

    }

    private function transformPaymentable($payment)
    {

        $new_arr = [];

        foreach($payment->paymentables as $paymentable)
        {

            $paymentable->payment_id = $this->encodePrimaryKey($paymentable->payment_id);
            $paymentable->paymentable_id = $this->encodePrimaryKey($paymentable->paymentable_id);

            $new_arr[] = $paymentable;
        }

        return $new_arr;

    }

    private function zipAndSend()
    {

        $file_name = date('Y-m-d').'_'.str_replace([" ", "/"],["_",""], $this->company->present()->name() . '_' . $this->company->company_key .'.zip');

        $path = 'backups';
        
        if(!Storage::disk(config('filesystems.default'))->exists($path))
            Storage::disk(config('filesystems.default'))->makeDirectory($path, 0775);

        $zip_path = public_path('storage/backups/'.$file_name);
        $zip = new \ZipArchive();

        if ($zip->open($zip_path, \ZipArchive::CREATE)!==TRUE) {
            nlog("cannot open {$zip_path}");
        }

        $zip->addFromString("backup.json", json_encode($this->export_data));
        $zip->close();

        if(Ninja::isHosted()) {
            Storage::disk(config('filesystems.default'))->put('backups/'.$file_name, file_get_contents($zip_path));
        }

        $storage_file_path = Storage::disk(config('filesystems.default'))->url('backups/'.$file_name);

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $company_reference = Company::find($this->company->id);;

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new DownloadBackup($storage_file_path, $company_reference);
        $nmo->to_user = $this->user;
        $nmo->company = $company_reference;
        $nmo->settings = $this->company->settings;
        
        NinjaMailerJob::dispatch($nmo, true);

        if(Ninja::isHosted()){
            sleep(3);
            unlink($zip_path);
        }
    }

}
