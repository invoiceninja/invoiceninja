<?php

use Faker\Generator as Faker;

$factory->define(App\Models\Gateway::class, function (Faker $faker) {
    return [
        'key' => '3b6621f970ab18887c4f6dca78d3f8bb',
        'visible' => true,
        'sort_order' =>1,
        'name' => 'demo',
        'provider' =>  'test',
        'is_offsite' => true,
        'is_secure' => true,
        'fields' => '',
        'default_gateway_type_id' => 1,
    ];
});
