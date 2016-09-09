<?php

use App\Models\GatewayType;

class GatewayTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();


        $gateway_types = [
            ['id' => 'credit_card', 'name' => 'Credit Card'],
            ['id' => 'bank_transfer', 'name' => 'Bank Transfer'],
            ['id' => 'paypal', 'name' => 'PayPal'],
            ['id' => 'bitcoin', 'name' => 'Bitcoin'],
            ['id' => 'dwolla', 'name' => 'Dwolla'],
        ];

        foreach ($gateway_types as $gateway_type) {
            $record = GatewayType::where('id', '=', $gateway_type['id'])->first();
            if (!$record) {
                GatewayType::create($gateway_type);
            }
        }

    }
}
