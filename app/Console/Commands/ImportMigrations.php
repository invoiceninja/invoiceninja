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
use App\Utils\Traits\MakesHash;
use Illuminate\Console\Command;

class ImportMigrations extends Command
{
    use MakesHash;
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
     * @var \Faker\Generator
     */
    private $faker;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->faker = \Faker\Factory::create();

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $path = $this->option('path') ?? storage_path('migrations/import');

        $directory = new \DirectoryIterator($path);

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

        $user = factory(\App\Models\User::class)->create([
            'account_id' => $account->id,
            'email' => $this->faker->email,
            'confirmation_code' => $this->createDbHash(config('database.default')),
        ]);

        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => \Illuminate\Support\Str::random(64),
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
        return factory(\App\Models\Account::class)->create();
    }

    public function getCompany(Account $account): Company
    {
        $company = factory(Company::class)->create([
            'account_id' => $account->id,
        ]);

        if (! $account->default_company_id) {
            $account->default_company_id = $company->id;
            $account->save();
        }

        return $company;
    }
}
