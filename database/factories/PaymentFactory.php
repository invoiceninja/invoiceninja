<?php

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\Payment::class, function (Faker $faker) {
    return [
		'is_deleted' => false,
		'amount' => $faker->numberBetween(1,10),
		'payment_date' => $faker->date(),
		'transaction_reference' => $faker->text(10),
		'invoice_id' => $faker->numberBetween(1,10)
    ];
});

		