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


use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceItemFactory;
use Faker\Generator as Faker;

$factory->define(App\Models\Expense::class, function (Faker $faker) {
    return [
        'amount' => $faker->numberBetween(1, 10),
        'custom_value1' => $faker->text(10),
        'custom_value2' => $faker->text(10),
        'custom_value3' => $faker->text(10),
        'custom_value4' => $faker->text(10),
        'exchange_rate' => $faker->randomFloat(2, 0, 1),
        'expense_date' => $faker->date(),
        'is_deleted' => false,
        'public_notes' => $faker->text(50),
        'private_notes' => $faker->text(50),
        'transaction_reference' => $faker->text(5),
    ];
});
