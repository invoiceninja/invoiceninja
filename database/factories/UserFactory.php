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

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'first_name'        => $this->faker->name(),
            'last_name'         => $this->faker->name(),
            'phone'             => $this->faker->phoneNumber(),
            'email'             => config('ninja.testvars.username'),
            'email_verified_at' => now(),
            'password'          => bcrypt(config('ninja.testvars.password')), // secret
            'remember_token'    => \Illuminate\Support\Str::random(10),
        ];
    }
}
