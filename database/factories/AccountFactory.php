<?php

use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(App\Models\Account::class, function (Faker $faker) {
    return [
        'default_company_id' => 1,
        'key' => Str::random(32),
    ];
});
