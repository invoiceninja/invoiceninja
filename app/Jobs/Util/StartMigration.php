<?php

namespace App\Jobs\Util;

use App\Exceptions\ProcessingMigrationArchiveFailed;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $zip = new \ZipArchive();
        $archive = $zip->open($this->filepath);

        $filename = pathinfo($this->filepath, PATHINFO_FILENAME);

        try {
            if ($archive) {
                $zip->extractTo(storage_path("migrations/{$filename}"));
                $zip->close();
            } else {
                throw new ProcessingMigrationArchiveFailed();
            }
        } catch (ProcessingMigrationArchiveFailed $e) {
            // TODO: Break the code, stop the migration.. send an e-mail.
        }

        // Rest of the migration..
    }
}
