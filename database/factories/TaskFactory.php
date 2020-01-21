<?php

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\Task::class, function (Faker $faker) {
    return [
        'description' => $faker->text(50),
    ];
});
