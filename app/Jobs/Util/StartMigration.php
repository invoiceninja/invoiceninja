<?php

namespace App\Jobs\Util;

use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\NonExistingMigrationFile;
use App\Exceptions\ResourceDependencyMissing;
use App\Mail\MigrationFailed;
use App\Models\User;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Exceptions\ProcessingMigrationArchiveFailed;
use App\Exceptions\ResourceNotAvailableForMigration;
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
        \Log::error("start handle");
        MultiDB::setDb($this->company->db);

        auth()->login($this->user, false);

        auth()->user()->setCompany($this->company);

        $zip = new \ZipArchive();
        $archive = $zip->open($this->filepath);

        $filename = pathinfo($this->filepath, PATHINFO_FILENAME);

        try {
            if (!$archive)
                throw new ProcessingMigrationArchiveFailed('Processing migration archive failed. Migration file is possibly corrupted.');

            $zip->extractTo(storage_path("migrations/{$filename}"));
            $zip->close();

            if (app()->environment() == 'testing')
                return;

            $this->start($filename);
        } catch (NonExistingMigrationFile | ProcessingMigrationArchiveFailed | ResourceNotAvailableForMigration | MigrationValidatorFailed | ResourceDependencyMissing $e) {
            Mail::to($this->user)->send(new MigrationFailed($e, $e->getMessage()));

            if (app()->environment() !== 'production') info($e->getMessage());
        }

        \Log::error("stop handle");
    }


    /**
     * Main method to start the migration.
     * @throws NonExistingMigrationFile
     */
    public function start(string $filename): void
    {

        \Log::error("start start");

        $file = storage_path("migrations/$filename/migration.json");

        if (!file_exists($file))
            throw new NonExistingMigrationFile('Migration file does not exist, or it is corrupted.');

        $handle = fopen($file, "r");
        $file = fread($handle, filesize($file));
        fclose($handle);

        $data = json_decode($file, 1);
        Import::dispatchNow($data, $this->company, $this->user);


        \Log::error("start stop");
    }
}
