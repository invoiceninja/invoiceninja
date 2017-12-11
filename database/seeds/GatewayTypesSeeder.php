<?php

use App\Models\GatewayType;

class GatewayTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $gateway_types = [
            ['alias' => 'credit_card', 'name' => 'Credit Card'],
            ['alias' => 'bank_transfer', 'name' => 'Bank Transfer'],
            ['alias' => 'paypal', 'name' => 'PayPal'],
            ['alias' => 'bitcoin', 'name' => 'Bitcoin'],
            ['alias' => 'dwolla', 'name' => 'Dwolla'],
            ['alias' => 'custom', 'name' => 'Custom'],
            ['alias' => 'alipay', 'name' => 'Alipay'],
            ['alias' => 'sofort', 'name' => 'Sofort'],
            ['alias' => 'sepa', 'name' => 'SEPA'],
            ['alias' => 'gocardless', 'name' => 'GoCardless'],
            ['alias' => 'apple_pay', 'name' => 'Apple Pay'],
        ];

        foreach ($gateway_types as $gateway_type) {
            $record = GatewayType::where('name', '=', $gateway_type['name'])->first();
            if (! $record) {
                GatewayType::create($gateway_type);
            }
        }
    }
}
