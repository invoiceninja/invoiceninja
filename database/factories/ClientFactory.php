<?php

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\Client::class, function (Faker $faker) {

    return [
        'name' => $faker->company(),
        'website' => $faker->url,
        'private_notes' => $faker->text(200),
        'balance' => 0,
        'paid_to_date' => 0,
        'vat_number' => $faker->numberBetween(123456789, 987654321),
        'id_number' => '',
        'custom_value1' => '',
        'custom_value2' => '',
        'custom_value3' => '',
        'custom_value4' => '',
        'address1' => $faker->buildingNumber,
        'address2' => $faker->streetAddress,
        'city' => $faker->city,
        'state' => $faker->state,
        'postal_code' => $faker->postcode,
        'country_id' => 4,
        'shipping_address1' => $faker->buildingNumber,
        'shipping_address2' => $faker->streetAddress,
        'shipping_city' => $faker->city,
        'shipping_state' => $faker->state,
        'shipping_postal_code' => $faker->postcode,
        'shipping_country_id' => 4,
        'settings' => ClientSettings::defaults(),
        'client_hash' => \Illuminate\Support\Str::random(40),
    ];
});
