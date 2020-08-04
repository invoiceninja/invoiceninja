<?php

use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(App\Models\InvoiceInvitation::class, function (Faker $faker) {
    return [
        'key' => Str::random(40),
    ];
});
