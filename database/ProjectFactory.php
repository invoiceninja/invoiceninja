<?php

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\Project::class, function (Faker $faker) {
    return [
        'name' => $faker->name(),
        'description' => $faker->text(50),
    ];
});
