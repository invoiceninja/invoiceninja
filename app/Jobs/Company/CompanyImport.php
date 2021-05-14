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
use ZipArchive;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class CompanyImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $company;

    public $file_path;

    private $backup_file;

    public $import_company;

    private $options = '';
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
    }

    public function handle()
    {
    	MultiDB::setDb($this->company->db);

    	$this->company =Company::where('company_key', $this->company->company_key)->firstOrFail();

    	$this->unzipFile()
    		 ->preFlightChecks();

    }


    //check if this is a complete company import OR if it is selective
    /*
     Company and settings only
     Data
     */
    
    private function preFlightChecks()
    {
    	//check the file version and perform any necessary adjustments to the file in order to proceed - needed when we change schema
    	//
    	$app_version = $this->backup_file->app_version;

    	nlog($app_version);

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

    private function importData()
    {
    	// $this->import_company = Company::where('company_key', $this->company->company_key)->firstOrFail();

    	return $this;
    }
}