<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Seeders;

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    use \App\Utils\Traits\MakesHash;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Running UsersTableSeeder');

        Model::unguard();

        $faker = \Faker\Factory::create();

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'domain' => 'ninja.test',
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => null,
            'is_locked' => 0,
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        ClientContact::create([
            'first_name' => $faker->firstName(),
            'last_name' => $faker->lastName(),
            'email' => config('ninja.testvars.clientname'),
            'company_id' => $company->id,
            'password' => Hash::make(config('ninja.testvars.password')),
            'email_verified_at' => now(),
            'client_id' =>$client->id,
        ]);

        Client::factory()->count(20)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company) {
            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->count(10)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
            ]);
        });
    }
}
