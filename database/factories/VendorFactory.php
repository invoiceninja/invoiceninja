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

$factory->define(App\Models\Vendor::class, function (Faker $faker) {
    return [
        'name' => $faker->name(),
        'website' => $faker->url,
        'private_notes' => $faker->text(200),
        'vat_number' => $faker->text(25),
        'id_number' => $faker->text(20),
        'custom_value1' => $faker->text(20),
        'custom_value2' => $faker->text(20),
        'custom_value3' => $faker->text(20),
        'custom_value4' => $faker->text(20),
        'address1' => $faker->buildingNumber,
        'address2' => $faker->streetAddress,
        'city' => $faker->city,
        'state' => $faker->state,
        'postal_code' => $faker->postcode,
        'country_id' => 4,
    ];
});
