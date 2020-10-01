<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Util;

use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\NonExistingMigrationFile;
use App\Exceptions\ProcessingMigrationArchiveFailed;
use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Libraries\MultiDB;
use App\Mail\MigrationFailed;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class StartMigration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $filepath;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Company
     */
    private $company;

    /**
     * Create a new job instance.
     *
     * @param $filepath
     * @param User $user
     * @param Company $company
     */
    public $tries = 1;

    public $timeout = 86400;

    public $backoff = 86430;

    public function __construct($filepath, User $user, Company $company)
    {
        $this->filepath = $filepath;
        $this->user = $user;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws ProcessingMigrationArchiveFailed
     * @throws NonExistingMigrationFile
     */
    public function handle()
    {
        set_time_limit(0);

        MultiDB::setDb($this->company->db);

        auth()->login($this->user, false);

        auth()->user()->setCompany($this->company);

        $this->company->setMigration(true);

        $zip = new \ZipArchive();
        $archive = $zip->open($this->filepath);

        $filename = pathinfo($this->filepath, PATHINFO_FILENAME);

        try {
            if (! $archive) {
                throw new ProcessingMigrationArchiveFailed('Processing migration archive failed. Migration file is possibly corrupted.');
            }

            $zip->extractTo(storage_path("migrations/{$filename}"));
            $zip->close();

            if (app()->environment() == 'testing') {
                return true;
            }

            $this->company->setMigration(true);

            $file = storage_path("migrations/$filename/migration.json");

            if (! file_exists($file)) {
                throw new NonExistingMigrationFile('Migration file does not exist, or it is corrupted.');
            }

            $data = json_decode(file_get_contents($file), 1);

            Import::dispatchNow($data, $this->company, $this->user);

            $this->company->setMigration(false);
        } catch (NonExistingMigrationFile | ProcessingMigrationArchiveFailed | ResourceNotAvailableForMigration | MigrationValidatorFailed | ResourceDependencyMissing $e) {
            $this->company->setMigration(false);

            Mail::to($this->user)->send(new MigrationFailed($e, $e->getMessage()));

            if (app()->environment() !== 'production') {
                info($e->getMessage());
            }
        }

        //always make sure we unset the migration as running

        return true;
    }

    public function failed($exception = null)
    {
    }
}
