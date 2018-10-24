<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        //require_once app_path() . '/Constants.php';

        $this->command->info('Running UserTableSeeder');

        Eloquent::unguard();

        $faker = Faker\Factory::create();

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::create([
            'account_id' => $account->id,
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => config('ninja.testvars.username'),
            'password' => Hash::make(config('ninja.testvars.password')),
            'email_verified_at' => now(),
        ]);

        $client = Client::create([
            'name' => $faker->name,
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

        \App\Models\UserCompany::create([
            'account_id' => $account->id,
            'company_id' => $company->id,
            'user_id' => $user->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
        ]);
    }
}
