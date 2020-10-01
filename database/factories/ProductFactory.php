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

$factory->define(App\Models\Product::class, function (Faker $faker) {
    return [
        'product_key' => $faker->text(7),
        'notes' => $faker->text(20),
        'cost' => $faker->numberBetween(1, 1000),
        'price' => $faker->numberBetween(1, 1000),
        'quantity' => $faker->numberBetween(1, 100),
        'tax_name1' => 'GST',
        'tax_rate1' => 10,
        'tax_name2' => 'VAT',
        'tax_rate2' => 17.5,
        'tax_name3' => 'THIRDTAX',
        'tax_rate3' => 5,
        'custom_value1' => $faker->text(20),
        'custom_value2' => $faker->text(20),
        'custom_value3' => $faker->text(20),
        'custom_value4' => $faker->text(20),
        'is_deleted' => false,
    ];
});
