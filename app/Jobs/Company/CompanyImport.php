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
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
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

    private $importables = [
        'company',
        'users',
        'payment_terms',
        'tax_rates',
        'expense_categories',
        'task_statuses',
        'clients',
        'client_contacts',
        'products',
        'vendors',
        'projects',
        'company_gateways',
        'client_gateway_tokens',
        'group_settings',
        'credits',
        'invoices',
        'recurring_invoices',
        'quotes',
        'payments',
        'subscriptions',
        'expenses',
        'tasks',
        'documents',
        'webhooks',
        'activities',
        'backups',
        'system_logs',
        'company_ledger',
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
        $this->hash = $hash;
        $this->request_array = $request_array;
        $this->current_app_version = config('ninja.app_version');
    }

    public function handle()
    {
    	MultiDB::setDb($this->company->db);

    	$this->company = Company::where('company_key', $this->company->company_key)->firstOrFail();
        $this->account = $this->company->account;

        $this->backup_file = Cache::get($this->hash);

        if ( empty( $this->import_object ) ) 
            throw new \Exception('No import data found, has the cache expired?');
        
        $this->backup_file = base64_decode($this->backup_file);


        /* Determine what we have to import now - should we also purge existing data? */


            // foreach($this->importables as $import){

            //     $method = Str::ucfirst(Str::camel($import));

            //     $this->{$method}();

            // }

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


    	return $this;
    }


    private function importSettings()
    {

        $this->company->settings = $this->backup_file->company->settings;
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

    private function importUsers()
    {
        User::unguard();

        foreach ($this->backup_file->users as $user)
        {

            if(User::where('email', $user->email)->where('account_id', '!=', $this->account->id)->exists())
                throw new ImportCompanyFailed("{$user->email} is already in the system attached to a different account");

            $new_user = User::firstOrNew(
                ['email' => $user->email],
                (array)$user,
            );

            $new_user->account_id = $this->account->id;
            $new_user->save(['timestamps' => false]);

            $this->ids['users']["{$user->hashed_id}"] = $new_user->id;

        }

        User::reguard();

    }

    private function importCompanyUsers()
    {
        CompanyUser::unguard();

        foreach($this->backup_file->company_users as $cu)
        {
            $user_id = $this->transformId($cu->user_id);

            $new_cu = CompanyUser::firstOrNew(
                        ['user_id' => $user_id, 'company_id', $this->company->id],
                        (array)$cu,
                    );

            $new_cu->account_id = $this->account->id;
            $new_cu->save(['timestamps' => false]);
            
        }

        CompanyUser::reguard();

    }


    public function transformId(string $resource, string $old): int
    {
        if (! array_key_exists($resource, $this->ids)) {
            throw new \Exception("Resource {$resource} not available.");
        }

        if (! array_key_exists("{$old}", $this->ids[$resource])) {
            throw new \Exception("Missing resource key: {$old}");
        }

        return $this->ids[$resource]["{$old}"];
    }
}