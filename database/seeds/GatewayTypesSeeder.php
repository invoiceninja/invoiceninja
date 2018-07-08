<?php

use App\Models\GatewayType;

class GatewayTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        // fix for legacy data
        DB::statement('UPDATE gateway_types SET alias = "custom1" WHERE id = 6');

        $gateway_types = [
            ['alias' => 'credit_card', 'name' => 'Credit Card'],
            ['alias' => 'bank_transfer', 'name' => 'Bank Transfer'],
            ['alias' => 'paypal', 'name' => 'PayPal'],
            ['alias' => 'bitcoin', 'name' => 'Bitcoin'],
            ['alias' => 'dwolla', 'name' => 'Dwolla'],
            ['alias' => 'custom1', 'name' => 'Custom'],
            ['alias' => 'alipay', 'name' => 'Alipay'],
            ['alias' => 'sofort', 'name' => 'Sofort'],
            ['alias' => 'sepa', 'name' => 'SEPA'],
            ['alias' => 'gocardless', 'name' => 'GoCardless'],
            ['alias' => 'apple_pay', 'name' => 'Apple Pay'],
            ['alias' => 'custom2', 'name' => 'Custom'],
            ['alias' => 'custom3', 'name' => 'Custom'],
        ];

        foreach ($gateway_types as $gateway_type) {
            $record = GatewayType::where('alias', '=', $gateway_type['alias'])->first();
            if ($record) {
                $record->fill($gateway_type);
                $record->save();
            } else {
                GatewayType::create($gateway_type);
            }
        }
    }
}
