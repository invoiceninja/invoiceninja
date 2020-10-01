<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Database\Factories;


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
        'remember_token'    => \Illuminate\Support\Str::random(10),
    ];
});
