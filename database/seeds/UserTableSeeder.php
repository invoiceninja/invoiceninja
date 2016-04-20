<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Affiliate;

class UserTableSeeder extends Seeder
{

	public function run()
	{
        $this->command->info('Running UserTableSeeder');

        Eloquent::unguard();

        $company = Company::create();
        
        $account = Account::create([
            //'name' => 'Test Account',
            'account_key' => str_random(RANDOM_KEY_LENGTH),
            'timezone_id' => 1,
            'company_id' => $company->id,
        ]);

        User::create([
            'email' => TEST_USERNAME,
            'username' => TEST_USERNAME,
            'account_id' => $account->id,
            'password' => Hash::make(TEST_PASSWORD),
            'registered' => true,
            'confirmed' => true,
        ]);

        Affiliate::create([
            'affiliate_key' => SELF_HOST_AFFILIATE_KEY
        ]);
        
	}

}