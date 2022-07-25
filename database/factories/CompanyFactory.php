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

use App\DataMapper\CompanySettings;
use App\Models\Company;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    use MakesHash;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            //'name' => $this->faker->name(),
            'company_key' => strtolower(\Illuminate\Support\Str::random(config('ninja.key_length'))),
            'ip' => $this->faker->ipv4(),
            'db' => config('database.default'),
            'settings' => CompanySettings::defaults(),
            'is_large' => false,
            'default_password_timeout' => 30 * 60000,
            'enabled_modules' => config('ninja.enabled_modules'),
            'custom_fields' => (object) [
            ],
            'company_key' => $this->createHash(),
        ];
    }
}
