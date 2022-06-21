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

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'is_deleted' => false,
            'amount' => $this->faker->numberBetween(1, 10),
            'date' => $this->faker->date(),
            'transaction_reference' => $this->faker->text(10),
            'type_id' => Payment::TYPE_CREDIT_CARD,
            'status_id' => Payment::STATUS_COMPLETED,
        ];
    }
}
