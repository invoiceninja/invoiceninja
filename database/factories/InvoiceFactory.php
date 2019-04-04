<?php

use Faker\Generator as Faker;

$factory->define(App\Models\Invoice::class, function (Faker $faker) {
    return [
		'invoice_status_id' => App\Models\Invoice::STATUS_PAID,
		'invoice_number' => $faker->text(20),
		'discount' => $faker->numberBetween(1,10),
		'is_amount_discount' => $faker->boolean(),
		'tax_name1' => 'GST',
		'tax_rate1' => 10,
		'tax_name2' => 'VAT',
		'tax_rate2' => 17.5,
		'custom_value1' => $faker->text(20),
		'custom_value2' => $faker->text(20),
		'custom_value3' => $faker->text(20),
		'custom_value4' => $faker->text(20),
		'is_deleted' => false,
		'po_number' => $faker->text(10),
		'invoice_date' => $faker->date(),
		'due_date' => $faker->date(),
		'line_items' => false,
		'options' => '',
		'backup' => '', 
    ];
});