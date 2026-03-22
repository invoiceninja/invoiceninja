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

use Illuminate\Database\Eloquent\Factories\Factory;

class BankTransactionRuleFactory extends Factory
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
            // 'applies_to' => (bool)rand(0,1) ? 'CREDIT' : 'DEBIT',
            'matches_on_all' => (bool)rand(0, 1),
            'auto_convert' => (bool)rand(0, 1),
        ];
    }
}
