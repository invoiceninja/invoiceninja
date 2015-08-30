<?php

use App\Models\User;
use App\Models\Account;

class UserTableSeeder extends Seeder
{

	public function run()
	{
        $this->command->info('Running UserTableSeeder');

        Eloquent::unguard();

        $account = Account::create([
            'name' => 'Test Account',
            'account_key' => str_random(16),
            'timezone_id' => 1,
        ]);

        User::create([
            'email' => TEST_USERNAME,
            'username' => TEST_USERNAME,
            'account_id' => $account->id,
            'password' => Hash::make(TEST_PASSWORD),
            'registered' => true,
            'confirmed' => true,
        ]);
	}

}