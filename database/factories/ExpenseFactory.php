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

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'amount' => $this->faker->numberBetween(1, 10),
            'custom_value1' => $this->faker->text(10),
            'custom_value2' => $this->faker->text(10),
            'custom_value3' => $this->faker->text(10),
            'custom_value4' => $this->faker->text(10),
            'exchange_rate' => $this->faker->randomFloat(2, 0, 1),
            'date' => $this->faker->date(),
            'is_deleted' => false,
            'public_notes' => $this->faker->text(50),
            'private_notes' => $this->faker->text(50),
            'transaction_reference' => $this->faker->text(5),
            'invoice_id' => null,
        ];
    }
}
