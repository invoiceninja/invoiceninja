<?php

namespace App\Jobs\Util;

use App\Models\User;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Exceptions\ProcessingMigrationArchiveFailed;

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
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $zip = new \ZipArchive();
        $archive = $zip->open($this->filepath);

        $filename = pathinfo($this->filepath, PATHINFO_FILENAME);


        try {
            if ($archive) {
                $zip->extractTo(storage_path("migrations/{$filename}"));
                $zip->close();

                if (app()->environment() !== 'testing') {
                    $this->start($filename);
                }
            } else {
                throw new ProcessingMigrationArchiveFailed();
            }
        } catch (ProcessingMigrationArchiveFailed $e) {
            // TODO: Break the code, stop the migration.. send an e-mail.
        }

        // Rest of the migration..
    }


    /**
     * Main method to start the migration.
     */
    protected function start(string $filename): void
    {
        $file = storage_path("migrations/$filename/migration.json");

        if (!file_exists($file))
            return;

        try {
            $handle = fopen($file, "r");
            $file = fread($handle, filesize($file));
            fclose($handle);

            $data = json_decode($file, 1);
            Import::dispatchNow($data, $this->company, $this->user);
        } catch (\Exception $e) {
            info($e->getMessage());
        }
    }
}
