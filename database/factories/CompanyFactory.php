<?php

use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\Company::class, function (Faker $faker) {
    return [
        //'name' => $faker->name,
        'company_key' => strtolower(\Illuminate\Support\Str::random(config('ninja.key_length'))),
        'ip' => $faker->ipv4,
        'db' => config('database.default'),
        'settings' => CompanySettings::defaults(),
        // 'address1' => $faker->secondaryAddress,
        // 'address2' => $faker->address,
        // 'city' => $faker->city,
        // 'state' => $faker->state,
        // 'postal_code' => $faker->postcode,
        // 'country_id' => 4,
        // 'phone' => $faker->phoneNumber,
        // 'email' => $faker->safeEmail,
        // 'logo' => 'https://www.invoiceninja.com/wp-content/themes/invoice-ninja/images/logo.png',
    ];
});
