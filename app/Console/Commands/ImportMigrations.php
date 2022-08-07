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

class ImportMigrations extends Command
{
    use MakesHash;
    use AppSetup;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:old-import {--path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Massively import the migrations.';

    /**
     * @var Generator
     */
    private $faker;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->faker = Factory::create();

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

        $path = $this->option('path') ?? public_path('storage/migrations/import');

        $directory = new DirectoryIterator($path);

        foreach ($directory as $file) {
            if ($file->getExtension() === 'zip') {
                $user = $this->getUser();
                $company = $this->getUser()->companies()->first();

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

                    Import::dispatch($import_file, $this->getUser()->companies()->first(), $this->getUser());
                    //   StartMigration::dispatch($file->getRealPath(), $this->getUser(), $this->getUser()->companies()->first());
                } catch (NonExistingMigrationFile | ProcessingMigrationArchiveFailed | ResourceNotAvailableForMigration | MigrationValidatorFailed | ResourceDependencyMissing $e) {
                    \Mail::to($this->user)->send(new MigrationFailed($e, $e->getMessage()));

                    if (app()->environment() !== 'production') {
                        info($e->getMessage());
                    }
                }
            }
        }
    }

    public function getUser(): User
    {
        $account = $this->getAccount();
        $company = $this->getCompany($account);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => Str::random(10).'@example.com',
            'confirmation_code' => $this->createDbHash($company->db),
        ]);

        CompanyToken::unguard();

        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'First token',
            'token' => Str::random(64),
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => '',
            'settings' => null,
        ]);

        return $user;
    }

    public function getAccount(): Account
    {
        return Account::factory()->create();
    }

    public function getCompany(Account $account): Company
    {
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'is_disabled' => true,
        ]);

        if (! $account->default_company_id) {
            $account->default_company_id = $company->id;
            $account->save();
        }

        return $company;
    }
}
