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

namespace App\Console\Commands;

use App\DataMapper\CompanySettings;
use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\NonExistingMigrationFile;
use App\Exceptions\ProcessingMigrationArchiveFailed;
use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Jobs\Util\Import;
use App\Jobs\Util\StartMigration;
use App\Libraries\MultiDB;
use App\Mail\MigrationFailed;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\MakesHash;
use DirectoryIterator;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ZipArchive;

class HostedMigrations extends Command
{
    use MakesHash;
    use AppSetup;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:import {--email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a v4 migration file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->buildCache();

        if (! MultiDB::userFindAndSetDb($this->option('email'))) {
            $this->info('Could not find a user with that email address');

            return;
        }

        $user = User::where('email', $this->option('email'))->first();

        if (! $user) {
            $this->info('There was a problem getting the user, did you set the right DB?');

            return;
        }

        $path = public_path('storage/migrations/import');

        $directory = new DirectoryIterator($path);

        foreach ($directory as $file) {
            if ($file->getExtension() === 'zip') {
                $company = $user->companies()->first();

                $this->info('Started processing: '.$file->getBasename().' at '.now());

                $zip = new ZipArchive();
                $archive = $zip->open($file->getRealPath());

                try {
                    if (! $archive) {
                        throw new ProcessingMigrationArchiveFailed('Processing migration archive failed. Migration file is possibly corrupted.');
                    }

                    $filename = pathinfo($file->getRealPath(), PATHINFO_FILENAME);

                    $zip->extractTo(public_path("storage/migrations/{$filename}"));
                    $zip->close();

                    $import_file = public_path("storage/migrations/$filename/migration.json");

                    Import::dispatch($import_file, $user->companies()->first(), $user);
                } catch (NonExistingMigrationFile | ProcessingMigrationArchiveFailed | ResourceNotAvailableForMigration | MigrationValidatorFailed | ResourceDependencyMissing $e) {
                    \Mail::to($this->user)->send(new MigrationFailed($e, $e->getMessage()));

                    if (app()->environment() !== 'production') {
                        info($e->getMessage());
                    }
                }
            }
        }
    }
}
