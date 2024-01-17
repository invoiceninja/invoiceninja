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

class BankIntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'integration_type' => null,
            'provider_name' => $this->faker->company(),
            'provider_id' => 1,
            'bank_account_name' => $this->faker->catchPhrase(),
            'bank_account_id' => 1,
            'bank_account_number' => $this->faker->randomNumber(9, true),
            'bank_account_status' => 'active',
            'bank_account_type' => 'creditCard',
            'balance' => $this->faker->randomFloat(2, 10, 10000),
            'currency' => 'USD',
            'nickname' => $this->faker->word(),
            'is_deleted' => false,
        ];
    }
}
