<?php

use App\Models\User;
use App\Models\Font;
use App\Models\Account;
use App\Models\Company;
use App\Models\Affiliate;
use App\Models\Country;
use App\Models\InvoiceDesign;
use Faker\Factory;

class UserTableSeeder extends Seeder
{

	public function run()
	{
        $this->command->info('Running UserTableSeeder');

        Eloquent::unguard();

        $faker = Faker\Factory::create();
        $company = Company::create();

        $account = Account::create([
            'name' => $faker->name,
            'address1' => $faker->streetAddress,
            'address2' => $faker->secondaryAddress,
            'city' => $faker->city,
            'state' => $faker->state,
            'postal_code' => $faker->postcode,
            'country_id' => Country::all()->random()->id,
            'account_key' => str_random(RANDOM_KEY_LENGTH),
            'invoice_terms' => $faker->text($faker->numberBetween(50, 300)),
            'work_phone' => $faker->phoneNumber,
            'work_email' => $faker->safeEmail,
            'invoice_design_id' => min(InvoiceDesign::all()->random()->id, 10),
            'header_font_id' => min(Font::all()->random()->id, 17),
            'body_font_id' => min(Font::all()->random()->id, 17),
            'primary_color' => $faker->hexcolor,
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
            'notify_sent' => false,
            'notify_paid' => false,
	        'is_admin' => 1,
        ]);

        Affiliate::create([
            'affiliate_key' => SELF_HOST_AFFILIATE_KEY
        ]);

	}

}
