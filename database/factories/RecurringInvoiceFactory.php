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

use App\Models\RecurringInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'discount' => $this->faker->numberBetween(1, 10),
            'is_amount_discount' => $this->faker->boolean(),
            'tax_name1' => 'GST',
            'tax_rate1' => 10,
            'tax_name2' => 'VAT',
            'tax_rate2' => 17.5,
            'tax_name3' => 'THIRDTAX',
            'tax_rate3' => 5,
            'custom_value1' => $this->faker->numberBetween(1, 4),
            'custom_value2' => $this->faker->numberBetween(1, 4),
            'custom_value3' => $this->faker->numberBetween(1, 4),
            'custom_value4' => $this->faker->numberBetween(1, 4),
            'is_deleted' => false,
            'po_number' => $this->faker->text(10),
            'date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'line_items' => false,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'last_sent_date' => now()->subMonth(),
            'next_send_date' => now()->addMonthNoOverflow(),
            'next_send_date_client' => now()->addMonthNoOverflow(),
            'remaining_cycles' => $this->faker->numberBetween(1, 10),
            'amount' => $this->faker->randomFloat(2, $min = 1, $max = 1000), // 48.8932

        ];
    }
}
