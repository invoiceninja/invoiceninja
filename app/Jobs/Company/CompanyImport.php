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

use App\Exceptions\ImportCompanyFailed;
use App\Exceptions\NonExistingMigrationFile;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Mail\DownloadBackup;
use App\Mail\DownloadInvoices;
use App\Mail\Import\CompanyImportFailure;
use App\Models\Activity;
use App\Models\Backup;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Paymentable;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\Webhook;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class CompanyImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    protected $current_app_version;

    private $account;

    public $company;

    public $user;

    private $hash;

    public $backup_file;

    public $ids = [];

    private $request_array = [];

    public $message = '';

    public $pre_flight_checks_pass = true;

    public $force_user_coalesce = false;

    public $company_owner;

    private $importables = [
        // 'company',
        'users',
        'company_users',
        'payment_terms',
        'tax_rates',
        'expense_categories',
        'task_statuses',
        'clients',
        'client_contacts',
        'vendors',
        'projects',
        'products',
        'company_gateways',
        'client_gateway_tokens',
        'group_settings',
        'subscriptions',
        'recurring_invoices',
        'recurring_invoice_invitations',
        'invoices',
        'invoice_invitations',
        'quotes',
        'quote_invitations',
        'credits',
        'credit_invitations',
        'expenses',
        'tasks',
        'payments',
        'activities',
        'backups',
        'company_ledger',
        'designs',
        'documents',
        'webhooks',
        'system_logs',
    ];

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param User $user
     * @param string $hash - the cache hash of the import data.
     * @param array $request->all()
     */
    public function __construct(Company $company, User $user, string $hash, array $request_array)
    {
        $this->company = $company;
        $this->user = $user;
        $this->hash = $hash;
        $this->request_array = $request_array;
        $this->current_app_version = config('ninja.app_version');
    }

    public function handle()
    {
    	MultiDB::setDb($this->company->db);

    	$this->company = Company::where('company_key', $this->company->company_key)->firstOrFail();
        $this->account = $this->company->account;
        $this->company_owner = $this->company->owner();

        nlog("Company ID = {$this->company->id}");
        nlog("Hash ID = {$this->hash}");

        $this->backup_file = Cache::get($this->hash);

        if ( empty( $this->backup_file ) ) 
            throw new \Exception('No import data found, has the cache expired?');
        
        $this->backup_file = json_decode(base64_decode($this->backup_file));

        // nlog($this->backup_file);
        $this->checkUserCount();

        if(array_key_exists('import_settings', $this->request_array) && $this->request_array['import_settings'] == 'true') {

            $this->preFlightChecks()->importSettings();
        }

        if(array_key_exists('import_data', $this->request_array) && $this->request_array['import_data'] == 'true') {

            try{

                $this->preFlightChecks()
                     ->purgeCompanyData()
                     ->importData();

             }
             catch(\Exception $e){

                info($e->getMessage());

             }

        }

    }

    /**
     * On the hosted platform we cannot allow the 
     * import to start if there are users > plan number
     * due to entity user_id dependencies
     *     
     * @return bool
     */
    private function checkUserCount()
    {

        if(Ninja::isSelfHost())
            $this->pre_flight_checks_pass = true;

        $backup_users = $this->backup_file->users;

        $company_users = $this->company->users;
        
            nlog("This is a free account");
            nlog("Backup user count = ".count($backup_users));

            if(count($backup_users) > 1){
                // $this->message = 'Only one user can be in the import for a Free Account';
                // $this->pre_flight_checks_pass = false;
                //$this->force_user_coalesce = true;
            }

            nlog("backup users email = " . $backup_users[0]->email);

            if(count($backup_users) == 1 && $this->company_owner->email != $backup_users[0]->email) {
                // $this->message = 'Account emails do not match. Account owner email must match backup user email';
                // $this->pre_flight_checks_pass = false;
                // $this->force_user_coalesce = true;
            }

            $backup_users_emails = array_column($backup_users, 'email');

            $company_users_emails = $company_users->pluck('email')->toArray();

            $existing_user_count = count(array_intersect($backup_users_emails, $company_users_emails));

            nlog("existing user count = {$existing_user_count}");

            if($existing_user_count > 1){

                if($this->account->plan == 'pro'){
                    // $this->message = 'Pro plan is limited to one user, you have multiple users in the backup file';
                    // $this->pre_flight_checks_pass = false;
                   // $this->force_user_coalesce = true;
                }

                if($this->account->plan == 'enterprise'){

                    $total_import_users = count($backup_users_emails);

                    $account_plan_num_user = $this->account->num_users;

                    if($total_import_users > $account_plan_num_user){
                        $this->message = "Total user count ({$total_import_users}) greater than your plan allows ({$account_plan_num_user})";
                        $this->pre_flight_checks_pass = false;
                    }

                }
            }

            if($this->company->account->isFreeHostedClient() && count($this->backup_file->clients) > config('ninja.quotas.free.clients')){
                
                nlog("client quota busted");

                $client_count = count($this->backup_file->clients);

                $client_limit = config('ninja.quotas.free.clients');

                $this->message = "You are attempting to import ({$client_count}) clients, your current plan allows a total of ({$client_limit})";
                
                $this->pre_flight_checks_pass = false;

            }

        return $this;
    }

    //check if this is a complete company import OR if it is selective
    /*
     Company and settings only
     Data
     */
    
    private function preFlightChecks()
    {
    	//check the file version and perform any necessary adjustments to the file in order to proceed - needed when we change schema
    	if($this->current_app_version != $this->backup_file->app_version)
        {
            //perform some magic here
        }
        
        if($this->pre_flight_checks_pass === false)
        {
            $nmo = new NinjaMailerObject;
            $nmo->mailable = new CompanyImportFailure($this->company, $this->message);
            $nmo->company = $this->company;
            $nmo->settings = $this->company->settings;
            $nmo->to_user = $this->company->owner();
            NinjaMailerJob::dispatchNow($nmo);

            nlog($this->message);
            throw new \Exception($this->message);
        }

    	return $this;
    }

    private function importSettings()
    {

        $this->company->settings = $this->backup_file->company->settings;
        $this->company->save();

        return $this;
    }

    private function purgeCompanyData()
    {
        $this->company->clients()->forceDelete();
        $this->company->products()->forceDelete();
        $this->company->projects()->forceDelete();
        $this->company->tasks()->forceDelete();
        $this->company->vendors()->forceDelete();
        $this->company->expenses()->forceDelete();
        $this->company->subscriptions()->forceDelete();

        $this->company->save();

        return $this;
    }

    private function importCompany()
    {
        $tmp_company = $this->backup_file->company;
        $tmp_company->company_key = $this->createHash();
        $tmp_company->db = config('database.default');
        $tmp_company->account_id = $this->account->id;

        if(Ninja::isHosted())
            $tmp_company->subdomain = MultiDB::randomSubdomainGenerator();
        else 
            $tmp_company->subdomain = '';

        $this->company = $tmp_company;
        $this->company->save();

    	return $this;
    }

    private function importData()
    {

        foreach($this->importables as $import){

            $method = "import_{$import}";

            nlog($method);

            $this->{$method}();

        }

            nlog("finished importing company data");

        return $this;

    }

    private function import_payment_terms()
    {

        $this->genericImport(PaymentTerm::class, 
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id'], 
            [['users' => 'user_id']], 
            'payment_terms',
            'num_days');

        return $this;

    }

    /* Cannot use generic as we are matching on two columns for existing data */
    private function import_tax_rates()
    {
        
        foreach($this->backup_file->tax_rates as $obj)
        {
        
            $user_id = $this->transformId('users', $obj->user_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);
            unset($obj_array['tax_rate_id']);

            $new_obj = TaxRate::firstOrNew(
                        ['name' => $obj->name, 'company_id' => $this->company->id, 'rate' => $obj->rate],
                        $obj_array,
                    );

            $new_obj->company_id = $this->company->id;
            $new_obj->user_id = $user_id;
            $new_obj->save(['timestamps' => false]);
            
        }

        return $this;
    }

    private function import_expense_categories()
    {

        $this->genericImport(ExpenseCategory::class, 
            ['user_id', 'company_id', 'id', 'hashed_id'], 
            [['users' => 'user_id']], 
            'expense_categories',
            'name');

        return $this;

    }

    private function import_task_statuses()
    {

        $this->genericImport(TaskStatus::class, 
            ['user_id', 'company_id', 'id', 'hashed_id'], 
            [['users' => 'user_id']], 
            'task_statuses',
            'name');

        return $this;
        
    }

    private function import_clients()
    {

        $this->genericImport(Client::class, 
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id', 'gateway_tokens', 'contacts', 'documents'], 
            [['users' => 'user_id'], ['users' => 'assigned_user_id']], 
            'clients',
            'number');

        return $this;
        
    }

    private function import_client_contacts()
    {

        $this->genericImport(ClientContact::class, 
            ['user_id', 'company_id', 'id', 'hashed_id'], 
            [['users' => 'user_id'], ['clients' => 'client_id']], 
            'client_contacts',
            'email');

        return $this;
        
    }

    private function import_vendors()
    {

        $this->genericImport(Vendor::class, 
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id'], 
            [['users' => 'user_id'], ['users' =>'assigned_user_id']], 
            'vendors',
            'number');

        return $this;
    }

    private function import_projects()
    {

        $this->genericImport(Project::class, 
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id','client_id'], 
            [['users' => 'user_id'], ['users' =>'assigned_user_id'], ['clients' => 'client_id']], 
            'projects',
            'number');
     
        return $this;   
    }

    private function import_products()
    {

        $this->genericNewClassImport(Product::class,
            ['user_id', 'company_id', 'hashed_id', 'id'],
            [['users' => 'user_id'], ['users' =>'assigned_user_id'], ['vendors' => 'vendor_id'], ['projects' => 'project_id']],
            'products' 
        );

        return $this;        
    }

    private function import_company_gateways()
    {

        $this->genericNewClassImport(CompanyGateway::class,
            ['user_id', 'company_id', 'hashed_id', 'id'],
            [['users' => 'user_id']],
            'company_gateways' 
        );

        return $this;        
    }

    private function import_client_gateway_tokens()
    {

        $this->genericNewClassImport(ClientGatewayToken::class, 
            ['company_id', 'id', 'hashed_id','client_id'], 
            [['clients' => 'client_id']], 
            'client_gateway_tokens');

        return $this;        
    }

    private function import_group_settings()
    {

        $this->genericImport(GroupSetting::class, 
            ['user_id', 'company_id', 'id', 'hashed_id',], 
            [['users' => 'user_id']], 
            'group_settings',
            'name');

        return $this;        
    }

    private function import_subscriptions()
    {
        
        $this->genericImport(Subscription::class, 
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id',], 
            [['group_settings' => 'group_id'], ['users' => 'user_id'], ['users' => 'assigned_user_id']], 
            'subscriptions',
            'name');

        return $this;        
    }

    private function import_recurring_invoices()
    {

        $this->genericImport(RecurringInvoice::class, 
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id', 'client_id','subscription_id','project_id','vendor_id','status'], 
            [
                ['subscriptions' => 'subscription_id'], 
                ['users' => 'user_id'], 
                ['users' => 'assigned_user_id'],
                ['clients' => 'client_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
                ['clients' => 'client_id'],
            ], 
            'recurring_invoices',
            'number');

        return $this;

    }

    private function import_recurring_invoice_invitations()
    {


        $this->genericImport(RecurringInvoiceInvitation::class, 
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'recurring_invoice_id'], 
            [
                ['users' => 'user_id'], 
                ['recurring_invoices' => 'recurring_invoice_id'],
                ['client_contacts' => 'client_contact_id'],
            ], 
            'recurring_invoice_invitations',
            'key');

        return $this;

    }

    private function import_invoices()
    {

        $this->genericImport(Invoice::class, 
            ['user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'recurring_id','status'], 
            [
                ['users' => 'user_id'], 
                ['users' => 'assigned_user_id'], 
                ['recurring_invoices' => 'recurring_id'],
                ['clients' => 'client_id'],
                ['subscriptions' => 'subscription_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ], 
            'invoices',
            'number');

        return $this;        
    }

    private function import_invoice_invitations()
    {


        $this->genericImport(InvoiceInvitation::class, 
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'invoice_id'], 
            [
                ['users' => 'user_id'], 
                ['invoices' => 'invoice_id'],
                ['client_contacts' => 'client_contact_id'],
            ], 
            'invoice_invitations',
            'key');

        return $this;        
    }

    private function import_quotes()
    {

        $this->genericImport(Quote::class, 
            ['user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'recurring_id','status'], 
            [
                ['users' => 'user_id'], 
                ['users' => 'assigned_user_id'], 
                ['recurring_invoices' => 'recurring_id'],
                ['clients' => 'client_id'],
                ['subscriptions' => 'subscription_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ], 
            'quotes',
            'number');

        return $this;

    }

    private function import_quote_invitations()
    {

        $this->genericImport(QuoteInvitation::class, 
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'quote_id'], 
            [
                ['users' => 'user_id'], 
                ['quotes' => 'quote_id'],
                ['client_contacts' => 'client_contact_id'],
            ], 
            'quote_invitations',
            'key');


        return $this;        
    }

    private function import_credits()
    {


        $this->genericImport(Credit::class, 
            ['user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'recurring_id','status'], 
            [
                ['users' => 'user_id'], 
                ['users' => 'assigned_user_id'], 
                ['recurring_invoices' => 'recurring_id'],
                ['clients' => 'client_id'],
                ['subscriptions' => 'subscription_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ], 
            'credits',
            'number');

            return $this;        
    }

    private function import_credit_invitations()
    {

        $this->genericImport(CreditInvitation::class, 
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'credit_id'], 
            [
                ['users' => 'user_id'], 
                ['credits' => 'credit_id'],
                ['client_contacts' => 'client_contact_id'],
            ], 
            'credit_invitations',
            'key');

            return $this;        
    }

    private function import_expenses()
    {


        $this->genericImport(Expense::class, 
            ['assigned_user_id', 'user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'project_id','vendor_id'], 
            [
                ['users' => 'user_id'], 
                ['users' => 'assigned_user_id'], 
                ['clients' => 'client_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ], 
            'expenses',
            'number');

        return $this;

    }

    private function import_tasks()
    {

        $this->genericImport(Task::class, 
            ['assigned_user_id', 'user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'invoice_id','project_id'], 
            [
                ['users' => 'user_id'], 
                ['users' => 'assigned_user_id'], 
                ['clients' => 'client_id'],
                ['projects' => 'project_id'],
                ['invoices' => 'invoice_id'],
            ], 
            'tasks',
            'number');

        return $this;        
    }

    private function import_payments()
    {

        $this->genericImport(Payment::class, 
            ['assigned_user_id', 'user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'client_contact_id','invitation_id','vendor_id','paymentables'], 
            [
                ['users' => 'user_id'], 
                ['users' => 'assigned_user_id'], 
                ['clients' => 'client_id'],
                ['client_contacts' => 'client_contact_id'],
                ['vendors' => 'vendor_id'],
                ['invoice_invitations' => 'invitation_id'],
                ['company_gateways' => 'company_gateway_id'],
            ], 
            'payments',
            'number');
        
        $this->paymentablesImport();

        return $this;
    }

    private function import_activities()
    {

        $activities = [];

        foreach($this->backup_file->activities as $activity)
        {
            $activity->account_id = $this->account->id;
            $activities[] = $activity;
        }

        $this->backup_file->activities = $activities;

        $this->genericNewClassImport(Activity::class, 
            [
                'hashed_id',
                'company_id',
            ], 
            [
                ['users' => 'user_id'], 
                ['clients' => 'client_id'],
                ['client_contacts' => 'client_contact_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
                ['payments' => 'payment_id'],
                ['invoices' => 'invoice_id'],
                ['credits' => 'credit_id'],
                ['tasks' => 'task_id'],
                ['expenses' => 'expense_id'],
                ['quotes' => 'quote_id'],
                ['subscriptions' => 'subscription_id'],
                ['recurring_invoices' => 'recurring_invoice_id'],
                ['invitations' => 'invitation_id'],
            ], 
            'activities');

        return $this;

    }

    private function import_backups()
    {

        $this->genericImportWithoutCompany(Backup::class, 
            ['hashed_id','id'], 
            [
                ['activities' => 'activity_id'], 
            ], 
            'backups',
            'created_at');


        return $this;        
    }  

    private function import_company_ledger()
    {

        $this->genericImport(CompanyLedger::class, 
            ['company_id', 'user_id', 'client_id', 'activity_id', 'id','account_id'], 
            [
                ['users' => 'user_id'], 
                ['clients' => 'client_id'],
                // ['activities' => 'activity_id'],
            ], 
            'company_ledger',
            'created_at');

        return $this;
        
    }

    private function import_designs()
    {
        
        $this->genericImport(Design::class, 
            ['company_id', 'user_id'], 
            [
                ['users' => 'user_id'],
            ], 
            'designs',
            'name');

        return $this;

    }

    private function import_documents()
    {

        foreach($this->backup_file->documents as $document)
        {

            $new_document = new Document();
            $new_document->user_id = $this->transformId('users', $document->user_id);
            $new_document->assigned_user_id = $this->transformId('users', $document->assigned_user_id);
            $new_document->company_id = $this->company->id;
            $new_document->project_id = $this->transformId('projects', $document->project_id);
            $new_document->vendor_id = $this->transformId('vendors', $document->vendor_id);
            $new_document->url = $document->url;
            $new_document->preview = $document->preview;
            $new_document->name = $document->name;
            $new_document->type = $document->type;
            $new_document->disk = $document->disk;
            $new_document->hash = $document->hash;
            $new_document->size = $document->size;
            $new_document->width = $document->width;
            $new_document->height = $document->height;
            $new_document->is_default = $document->is_default;
            $new_document->custom_value1 = $document->custom_value1;
            $new_document->custom_value2 = $document->custom_value2;
            $new_document->custom_value3 = $document->custom_value3;
            $new_document->custom_value4 = $document->custom_value4;
            $new_document->deleted_at = $document->deleted_at;
            $new_document->documentable_id = $this->transformDocumentId($document->documentable_id, $document->documentable_type);
            $new_document->documentable_type = $document->documentable_type;

            $new_document->save(['timestamps' => false]);
        
        }

        return $this;
    }

    private function import_webhooks()
    {

        $this->genericImport(Webhook::class, 
            ['company_id', 'user_id'], 
            [
                ['users' => 'user_id'],
            ], 
            'webhooks',
            'created_at');

        return $this;
    }


    private function import_system_logs()
    {
        return $this;
    }

    private function import_users()
    {
        User::unguard();

        foreach ($this->backup_file->users as $user)
        {

            if(User::where('email', $user->email)->where('account_id', '!=', $this->account->id)->exists())
                throw new ImportCompanyFailed("{$user->email} is already in the system attached to a different account");

            $user_array = (array)$user;
            unset($user_array['laravel_through_key']);
            unset($user_array['hashed_id']);
            unset($user_array['id']);

            $new_user = User::firstOrNew(
                ['email' => $user->email],
                $user_array,
            );

            $new_user->account_id = $this->account->id;
            $new_user->save(['timestamps' => false]);

            $this->ids['users']["{$user->hashed_id}"] = $new_user->id;

        }

        User::reguard();

    }

    private function import_company_users()
    {
        CompanyUser::unguard();

        foreach($this->backup_file->company_users as $cu)
        {
            $user_id = $this->transformId('users', $cu->user_id);

            $cu_array = (array)$cu;
            unset($cu_array['id']);

            $new_cu = CompanyUser::firstOrNew(
                        ['user_id' => $user_id, 'company_id' => $this->company->id],
                        $cu_array,
                    );

            $new_cu->account_id = $this->account->id;
            $new_cu->save(['timestamps' => false]);
            
        }

        CompanyUser::reguard();

    }



    private function transformDocumentId($id, $type)
    {
        switch ($type) {
            case Company::class:
                return $this->company->id;
                break;
            case Client::class:
                return $this->transformId('clients', $id);
                break;
            case ClientContact::class:
                return $this->transformId('client_contacts', $id);
                break;
            case Credit::class:
                return $this->transformId('credits', $id);
                break;
            case Expense::class:
                return $this->transformId('expenses', $id);
                break;
            case 'invoices':
                return $this->transformId('invoices', $id);
                break;
            case Payment::class:
                return $this->transformId('payments', $id);
                break;
            case Product::class:
                return $this->transformId('products', $id);
                break;
            case Quote::class:
                return $this->transformId('quotes', $id);
                break;
            case RecurringInvoice::class:
                return $this->transformId('recurring_invoices', $id);
                break;
            case Company::class:
                return $this->transformId('clients', $id);
                break;

            
            default:
                # code...
                break;
        }
    }

    private function paymentablesImport()
    {

        foreach($this->backup_file->payments as $payment)
        {

            foreach($payment->paymentables as $paymentable_obj)
            {

                $paymentable = new Paymentable();
                $paymentable->payment_id = $this->transformId('payments', $paymentable_obj->payment_id);
                $paymentable->paymentable_type = $paymentable_obj->paymentable_type;
                $paymentable->amount = $paymentable_obj->amount;
                $paymentable->refunded = $paymentable_obj->refunded;
                $paymentable->created_at = $paymentable_obj->created_at;
                $paymentable->deleted_at = $paymentable_obj->deleted_at;
                $paymentable->updated_at = $paymentable_obj->updated_at;
                $paymentable->paymentable_id = $this->convertPaymentableId($paymentable_obj->paymentable_type, $paymentable_obj->paymentable_id);
                $paymentable->paymentable_type = $paymentable_obj->paymentable_type;
                $paymentable->save(['timestamps' => false]);
            }
        }

        return $this;
    }

    private function convertPaymentableId($type, $id)
    {
        switch ($type) {
            case 'invoices':
                return $this->transformId('invoices', $id);
                break;
            case Credit::class:
                return $this->transformId('credits', $id);
                break;    
            case Payment::class:
                return $this->transformId('payments', $id);        
            default:
                # code...
                break;
        }
    }


    private function genericNewClassImport($class, $unset, $transforms, $object_property)
    {

        $class::unguard();

        foreach($this->backup_file->{$object_property} as $obj)
        {
            /* Remove unwanted keys*/
            $obj_array = (array)$obj;
            foreach($unset as $un){
                unset($obj_array[$un]);
            }

            $activity_invitation_key = false;

            if($class == 'App\Models\Activity'){

                if(isset($obj->invitation_id)){

                    if(isset($obj->invoice_id))
                        $activity_invitation_key = 'invoice_invitations';
                    elseif(isset($obj->quote_id))
                        $activity_invitation_key = 'quote_invitations';
                    elseif($isset($obj->credit_id))
                        $activity_invitation_key  = 'credit_invitations';

                }

            }

            /* Transform old keys to new keys */
            foreach($transforms as $transform)
            {
                foreach($transform as $key => $value)
                {
                    if($class == 'App\Models\Activity' && $activity_invitation_key && $key == 'invitations'){
                        $key = $activity_invitation_key;
                    }
                    
                    $obj_array["{$value}"] = $this->transformId($key, $obj->{$value});
                }    
            }

            if($class == 'App\Models\CompanyGateway') {
                $obj_array['config'] = encrypt($obj_array['config']);
            }

            $new_obj = new $class();
            $new_obj->company_id = $this->company->id;
            $new_obj->fill($obj_array);

            $new_obj->save(['timestamps' => false]);
            
            $this->ids["{$object_property}"]["{$obj->hashed_id}"] = $new_obj->id;

        }

        $class::reguard();
    

    }

    private function genericImportWithoutCompany($class, $unset, $transforms, $object_property, $match_key)
    {

        $class::unguard();

        foreach($this->backup_file->{$object_property} as $obj)
        {
            /* Remove unwanted keys*/
            $obj_array = (array)$obj;
            foreach($unset as $un){
                unset($obj_array[$un]);
            }

            /* Transform old keys to new keys */
            foreach($transforms as $transform)
            {
                foreach($transform as $key => $value)
                {
                    $obj_array["{$value}"] = $this->transformId($key, $obj->{$value});
                }    
            }
            
            /* New to convert product ids from old hashes to new hashes*/
            if($class == 'App\Models\Subscription'){
                $obj_array['product_ids'] = $this->recordProductIds($obj_array['product_ids']); 
                $obj_array['recurring_product_ids'] = $this->recordProductIds($obj_array['recurring_product_ids']); 
            }

            $new_obj = $class::firstOrNew(
                    [$match_key => $obj->{$match_key}],
                    $obj_array,
                );

            $new_obj->save(['timestamps' => false]);
            
            if($new_obj instanceof CompanyLedger){

            }
            else
                $this->ids["{$object_property}"]["{$obj->hashed_id}"] = $new_obj->id;

        }

        $class::reguard();
    
    }


    private function genericImport($class, $unset, $transforms, $object_property, $match_key)
    {

        $class::unguard();

        foreach($this->backup_file->{$object_property} as $obj)
        {
            /* Remove unwanted keys*/
            $obj_array = (array)$obj;
            foreach($unset as $un){
                unset($obj_array[$un]);
            }

            /* Transform old keys to new keys */
            foreach($transforms as $transform)
            {
                foreach($transform as $key => $value)
                {
                    $obj_array["{$value}"] = $this->transformId($key, $obj->{$value});
                }    
            }
            
            /* New to convert product ids from old hashes to new hashes*/
            if($class == 'App\Models\Subscription'){
                $obj_array['product_ids'] = $this->recordProductIds($obj_array['product_ids']); 
                $obj_array['recurring_product_ids'] = $this->recordProductIds($obj_array['recurring_product_ids']); 
            }

            $new_obj = $class::firstOrNew(
                    [$match_key => $obj->{$match_key}, 'company_id' => $this->company->id],
                    $obj_array,
                );

            $new_obj->save(['timestamps' => false]);
            
            if($new_obj instanceof CompanyLedger){
            }
            else
                $this->ids["{$object_property}"]["{$obj->hashed_id}"] = $new_obj->id;

        }

        $class::reguard();
    
    }

    private function recordProductIds($ids)
    {

        $id_array = explode(",", $ids);

        $tmp_arr = [];

        foreach($id_array as $id) {

            $tmp_arr[] = $this->encodePrimaryKey($this->transformId('products', $id));
        }     

        return implode(",", $tmp_arr);
    }

    /* Transform all IDs from old to new
     *
     * In the case of users - we need to check if the system
     * is attempting to migrate resources above their quota,
     *
     * ie. > 50 clients or more than 1 user 
    */
    private function transformId(string $resource, ?string $old): ?int
    {
        if(empty($old))
            return null;

        if ($resource == 'users' && $this->force_user_coalesce){
            return $this->company_owner->id;
        }

        if (! array_key_exists($resource, $this->ids)) {
            // nlog($this->ids);
            throw new \Exception("Resource {$resource} not available.");
        }

        if (! array_key_exists("{$old}", $this->ids[$resource])) {
            // nlog($this->ids[$resource]);
            nlog("searching for {$old} in {$resource}");
            throw new \Exception("Missing {$resource} key: {$old}");
        }

        return $this->ids[$resource]["{$old}"];
    }


}