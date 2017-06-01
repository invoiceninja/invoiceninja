<?php

use App\Models\Account;
use App\Models\AccountEmailSettings;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Country;
use App\Models\DateFormat;
use App\Models\Font;
use App\Models\InvoiceDesign;
use App\Models\Product;
use App\Models\User;

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
            'account_key' => strtolower(str_random(RANDOM_KEY_LENGTH)),
            'invoice_terms' => $faker->text($faker->numberBetween(50, 300)),
            'work_phone' => $faker->phoneNumber,
            'work_email' => $faker->safeEmail,
            'invoice_design_id' => InvoiceDesign::where('id', '<', CUSTOM_DESIGN1)->get()->random()->id,
            'header_font_id' => min(Font::all()->random()->id, 17),
            'body_font_id' => min(Font::all()->random()->id, 17),
            'primary_color' => $faker->hexcolor,
            'timezone_id' => 58,
            'company_id' => $company->id,
            'pdf_email_attachment' => true,
        ]);

        $emailSettings = AccountEmailSettings::create([
            'account_id' => $account->id
        ]);

        $user = User::create([
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
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

        $client = Client::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'public_id' => 1,
            'name' => $faker->name,
            'address1' => $faker->streetAddress,
            'address2' => $faker->secondaryAddress,
            'city' => $faker->city,
            'state' => $faker->state,
            'postal_code' => $faker->postcode,
            'country_id' => DEFAULT_COUNTRY,
            'currency_id' => DEFAULT_CURRENCY,
        ]);

        Contact::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'client_id' => $client->id,
            'public_id' => 1,
            'email' => env('TEST_EMAIL', TEST_USERNAME),
            'is_primary' => true,
            'send_invoice' => true,
            'contact_key' => strtolower(str_random(RANDOM_KEY_LENGTH)),
        ]);

        Product::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'public_id' => 1,
            'product_key' => 'ITEM',
            'notes' => 'Something nice...',
            'cost' => 10,
        ]);

        Affiliate::create([
            'affiliate_key' => SELF_HOST_AFFILIATE_KEY,
        ]);
    }
}
