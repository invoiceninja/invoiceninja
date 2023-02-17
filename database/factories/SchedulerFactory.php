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

class SchedulerFactory extends Factory
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
            'is_paused' => rand(0, 1),
            'is_deleted' => rand(0, 1),
            'parameters' => [],
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_run' => now()->addSeconds(rand(86400, 8640000)),
            'next_run_client' => now()->addSeconds(rand(86400, 8640000)),
            'template' => 'client_statement',
        ];
    }
}
