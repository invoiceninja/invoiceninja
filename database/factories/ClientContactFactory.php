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

$factory->define(App\Models\ClientContact::class, function (Faker $faker) {
    return [
        'first_name' => $faker->firstName,
        'last_name' => $faker->lastName,
        'phone' => $faker->phoneNumber,
        'email_verified_at' => now(),
        'email' => $faker->unique()->safeEmail,
        'send_email' => true,
        'password' => bcrypt('password'),
        'remember_token' => \Illuminate\Support\Str::random(10),
        'contact_key' => \Illuminate\Support\Str::random(40),
    ];
});
