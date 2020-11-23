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

namespace App\Console\Commands;

use App\DataMapper\CompanySettings;
use App\Jobs\Util\StartMigration;
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

class ImportMigrations extends Command
{
    use MakesHash;
    use AppSetup;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrations:import {--path=}';

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
        
        $path = $this->option('path') ?? storage_path('migrations/import');

        $directory = new DirectoryIterator($path);

        foreach ($directory as $file) {
            if ($file->getExtension() === 'zip') {
                $this->info('Started processing: '.$file->getBasename().' at '.now());
                StartMigration::dispatch($file->getRealPath(), $this->getUser(), $this->getUser()->companies()->first());
            }
        }
    }

    public function getUser(): User
    {
        $account = $this->getAccount();
        $company = $this->getCompany($account);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => Str::random(10) . "@example.com",
            'confirmation_code' => $this->createDbHash(config('database.default')),
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
