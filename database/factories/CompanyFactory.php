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

use App\DataMapper\CompanySettings;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            //'name' => $this->faker->name,
            'company_key' => strtolower(\Illuminate\Support\Str::random(config('ninja.key_length'))),
            'ip' => $this->faker->ipv4,
            'db' => config('database.default'),
            'settings' => CompanySettings::defaults(),
            'is_large' => false,
            'enabled_modules' => config('ninja.enabled_modules'),
            'custom_fields' => (object) [
                //'invoice1' => 'Custom Date|date',
                // 'invoice2' => '2|switch',
                // 'invoice3' => '3|',
                // 'invoice4' => '4',
                // 'client1'=>'1',
                // 'client2'=>'2',
                // 'client3'=>'3|date',
                // 'client4'=>'4|switch',
                // 'company1'=>'1|date',
                // 'company2'=>'2|switch',
                // 'company3'=>'3',
                // 'company4'=>'4',
            ],
        ];
    }
}
