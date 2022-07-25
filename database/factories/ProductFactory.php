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

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'product_key' => $this->faker->text(7),
            'notes' => $this->faker->text(20),
            'cost' => $this->faker->numberBetween(1, 1000),
            'price' => $this->faker->numberBetween(1, 1000),
            'quantity' => $this->faker->numberBetween(1, 100),
            // 'tax_name1' => 'GST',
            // 'tax_rate1' => 10,
            // 'tax_name2' => 'VAT',
            // 'tax_rate2' => 17.5,
            // 'tax_name3' => 'THIRDTAX',
            // 'tax_rate3' => 5,
            'custom_value1' => $this->faker->text(20),
            'custom_value2' => $this->faker->text(20),
            'custom_value3' => $this->faker->text(20),
            'custom_value4' => $this->faker->text(20),
            'is_deleted' => false,
        ];
    }
}
