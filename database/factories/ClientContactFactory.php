<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Database\Factories;

use App\Models\ClientContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ClientContact::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'phone' => $this->faker->phoneNumber,
            'email_verified_at' => now(),
            'email' => $this->faker->unique()->safeEmail,
            'send_email' => true,
            'password' => bcrypt('password'),
            'remember_token' => \Illuminate\Support\Str::random(10),
            'contact_key' => \Illuminate\Support\Str::random(40),
        ];
    }
}
