<?php

use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\Company::class, function (Faker $faker) {
    return [
        //'name' => $faker->name,
        'company_key' => strtolower(\Illuminate\Support\Str::random(config('ninja.key_length'))),
        'ip' => $faker->ipv4,
        'db' => config('database.default'),
        'settings' => CompanySettings::defaults(),
        'is_large' => false,
        'custom_fields' => (object) [
            //'invoice1' => 'Custom Date|date',
            // 'invoice2' => '2|switch',
            // 'invoice3' => '3|',
            // 'invoice4' => '4',
            // 'client1'=>'1',
            // 'client2'=>'2',
            // 'client3'=>'3|date',
            // 'client4'=>'4|switch',
            // 'company1'=>'1|date',
            // 'company2'=>'2|switch',
            // 'company3'=>'3',
            // 'company4'=>'4',
        ],
    ];
});
