<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'website' => $this->faker->url(),
            'private_notes' => $this->faker->text(200),
            'vat_number' => $this->faker->text(25),
            'id_number' => $this->faker->text(20),
            'custom_value1' => $this->faker->text(20),
            'custom_value2' => $this->faker->text(20),
            'custom_value3' => $this->faker->text(20),
            'custom_value4' => $this->faker->text(20),
            'address1' => $this->faker->buildingNumber(),
            'address2' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country_id' => 4,
            'vendor_hash' => Str::random(40),

        ];
    }
}
