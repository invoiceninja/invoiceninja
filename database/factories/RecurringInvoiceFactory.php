<?php

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Faker\Generator as Faker;

$factory->define(App\Models\RecurringInvoice::class, function (Faker $faker) {
    return [
        'status_id' => App\Models\RecurringInvoice::STATUS_ACTIVE,
        'discount' => $faker->numberBetween(1, 10),
        'is_amount_discount' => $faker->boolean(),
        'tax_name1' => 'GST',
        'tax_rate1' => 10,
        'tax_name2' => 'VAT',
        'tax_rate2' => 17.5,
        'tax_name3' => 'THIRDTAX',
        'tax_rate3' => 5,
        'custom_value1' => $faker->numberBetween(1, 4),
        'custom_value2' => $faker->numberBetween(1, 4),
        'custom_value3' => $faker->numberBetween(1, 4),
        'custom_value4' => $faker->numberBetween(1, 4),
        'is_deleted' => false,
        'po_number' => $faker->text(10),
        'date' => $faker->date(),
        'due_date' => $faker->date(),
        'line_items' => false,
        'frequency_id' => App\Models\RecurringInvoice::FREQUENCY_MONTHLY,
        'last_sent_date' => now()->subMonth(),
        'next_send_date' => now()->addMonthNoOverflow(),
        'remaining_cycles' => $faker->numberBetween(1, 10),
        'amount' => $faker->randomFloat(2, $min = 1, $max = 1000), // 48.8932

    ];
});
