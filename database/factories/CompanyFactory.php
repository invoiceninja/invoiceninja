<?php

use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\Company::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'company_key' => strtolower(str_random(config('ninja.key_length'))),
        'ip' => $faker->ipv4,
        'db' => config('database.default'),
        'settings' => new CompanySettings(CompanySettings::defaults()),
    ];
});
