<?php

use Faker\Generator as Faker;

$factory->define(App\Models\Company::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'company_key' => strtolower(str_random(RANDOM_KEY_LENGTH)),
        'ip' => $faker->ipv4,
        'db' => config('database.default'),
    ];
});
