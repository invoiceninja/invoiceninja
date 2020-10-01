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
use App\Models\Payment;
use Faker\Generator as Faker;

$factory->define(App\Models\Payment::class, function (Faker $faker) {
    return [
        'is_deleted' => false,
        'amount' => $faker->numberBetween(1, 10),
        'date' => $faker->date(),
        'transaction_reference' => $faker->text(10),
        'type_id' => Payment::TYPE_CREDIT_CARD,
        'status_id' => Payment::STATUS_COMPLETED,
    ];
});
