<?php

use App\Models\GatewayType;

class GatewayTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();


        $gateway_types = [
            ['name' => 'Credit Card'],
            ['name' => 'Bank Transfer'],
            ['name' => 'PayPal'],
            ['name' => 'Bitcoin'],
            ['name' => 'Dwolla'],
        ];

        foreach ($gateway_types as $gateway_type) {
            $record = GatewayType::where('name', '=', $gateway_type['name'])->first();
            if (!$record) {
                GatewayType::create($gateway_type);
            }
        }

    }
}
