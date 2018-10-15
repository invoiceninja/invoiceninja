<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Contact;
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

        require_once app_path() . '/Constants.php';

        $this->command->info('Running UserTableSeeder');

        Eloquent::unguard();

        $faker = Faker\Factory::create();

        $account = Account::create([
            'name' => $faker->name(),
        ]);

        $user = User::create([
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => TEST_USERNAME,
            'account_id' => $account->id,
            'password' => Hash::make(TEST_PASSWORD),
            'registered' => true,
            'confirmed' => true,
        ]);

        $client = Client::create([
            'name' => $faker->name,
            'account_id' => $account->id,
        ]);

        Contact::create([
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => TEST_CLIENTNAME,
            'account_id' => $account->id,
            'password' => Hash::make(TEST_PASSWORD),
            'registered' => true,
            'confirmed' => true,
            'client_id' =>$client->id,
        ]);

        UserAccount::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'is_default' => 1,
        ]);
    }
}
