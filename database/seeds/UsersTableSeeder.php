<?php

use App\DataMapper\CompanySettings;
use App\DataMapper\DefaultSettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Models\UserAccount;
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

        Eloquent::unguard();

        $faker = \Faker\Factory::create();

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
            'domain' => 'ninja.test',
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = factory(\App\Models\User::class)->create([
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

        $client = factory(\App\Models\Client::class)->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        ClientContact::create([
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => config('ninja.testvars.clientname'),
            'company_id' => $company->id,
            'password' => Hash::make(config('ninja.testvars.password')),
            'email_verified_at' => now(),
            'client_id' =>$client->id,
        ]);

        factory(\App\Models\Client::class, 20)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company) {
            factory(\App\Models\ClientContact::class, 1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

            factory(\App\Models\ClientContact::class, 10)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
            ]);
        });
    }
}
