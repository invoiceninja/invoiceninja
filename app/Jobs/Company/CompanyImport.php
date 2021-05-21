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

use App\Exceptions\NonExistingMigrationFile;
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
use Illuminate\Support\Str;
use ZipArchive;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class CompanyImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    protected $current_app_version;

    public $company;

    private $account;

    public $file_path;

    private $backup_file;

    public $import_company;

    public $ids = [];

    private $options = '';

    private $importables = [
        'company',
        'users',
        // 'payment_terms',
        // 'tax_rates',
        // 'clients',
        // 'company_gateways',
        // 'client_gateway_tokens',
        // 'vendors',
        // 'projects',
        // 'products',
        // 'credits',
        // 'invoices',
        // 'recurring_invoices',
        // 'quotes',
        // 'payments',
        // 'expense_categories',
        // 'task_statuses',
        // 'expenses',
        // 'tasks',
        // 'documents',
    ];

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param User $user
     * @param string $custom_token_name
     */
    public function __construct(Company $company, string $file_path, array $options)
    {
        $this->company = $company;
        $this->file_path = $file_path;
        $this->options = $options;
        $this->current_app_version = config('ninja.app_version');
    }

    public function handle()
    {
    	MultiDB::setDb($this->company->db);

    	$this->company =Company::where('company_key', $this->company->company_key)->firstOrFail();
        $this->account = $this->company->account;

    	$this->unzipFile()
    		 ->preFlightChecks();

            foreach($this->importables as $import){

                $method = Str::ucfirst(Str::camel($import));

                $this->{$method}();

            }

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

    private function unzipFile()
    {
        $zip = new ZipArchive();
    	$archive = $zip->open(public_path("storage/backups/{$this->file_path}"));
    	$filename = pathinfo($this->filepath, PATHINFO_FILENAME);
        $zip->extractTo(public_path("storage/backups/{$filename}"));
        $zip->close();
        $file_location = public_path("storage/backups/$filename/backup.json");

        if (! file_exists($file_location)) {
            throw new NonExistingMigrationFile('Backup file does not exist, or it is corrupted.');
        }

    	$this->backup_file = json_decode(file_get_contents($file_location));

    	return $this;
    }

    private function importCompany()
    {

    	//$this->import_company = ..
    	return $this;
    }

    private function importUsers()
    {
        User::unguard();

        foreach ($this->backup_file->users as $user)
        {

            $new_user = User::firstOrNew(
                ['email' => $user->email],
                (array)$user,
            );

            $new_user->account_id = $this->account->id;
            $new_user->save(['timestamps' => false]);

            $this->ids['users']["{$user->id}"] = $new_user->id;
        }

        Expense::reguard();

    }


    public function transformId(string$resource, string $old): int
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