<?php

use Faker\Generator as Faker;

$factory->define(App\Models\ClientLocation::class, function (Faker $faker) {
    return [
        'address1' => $faker->buildingNumber,
        'address2' => $faker->streetAddress,
        'city' => $faker->city,
        'state' => $faker->state,
        'postal_code' => $faker->postcode,
        'country_id' => 4,
        'latitude' => $faker->latitude,
        'longitude' => $faker->longitude,
        'description' => $faker->paragraph,
        'private_notes' => $faker->paragraph,
    ];
});
