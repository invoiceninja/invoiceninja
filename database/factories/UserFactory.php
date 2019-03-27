<?php

use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\Models\User::class, function (Faker $faker) {
    return [
        'first_name'        => $faker->name,
        'last_name'         => $faker->name,
        'phone'             => $faker->phoneNumber,
        'email'             => config('ninja.testvars.username'),
        'email_verified_at' => now(),
        'password'          => bcrypt(config('ninja.testvars.password')), // secret
        'remember_token'    => str_random(10),
    ];
});
