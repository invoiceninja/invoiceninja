<?php

use App\Models\PaymentType;

class PaymentTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $paymentTypes = [
            ['name' => 'Apply Credit'],
            ['name' => 'Bank Transfer', 'gateway_type_id' => GATEWAY_TYPE_BANK_TRANSFER],
            ['name' => 'Cash'],
            ['name' => 'Debit', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'ACH', 'gateway_type_id' => GATEWAY_TYPE_BANK_TRANSFER],
            ['name' => 'Visa Card', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'MasterCard', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'American Express', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Discover Card', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Diners Card', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'EuroCard', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Nova', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Credit Card Other', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'PayPal', 'gateway_type_id' => GATEWAY_TYPE_PAYPAL],
            ['name' => 'Google Wallet'],
            ['name' => 'Check'],
            ['name' => 'Carte Blanche', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'UnionPay', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'JCB', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Laser', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Maestro', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Solo', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Switch', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'iZettle', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD],
            ['name' => 'Swish', 'gateway_type_id' => GATEWAY_TYPE_BANK_TRANSFER],
            ['name' => 'Venmo'],
            ['name' => 'Money Order'],
        ];

        foreach ($paymentTypes as $paymentType) {
            $record = PaymentType::where('name', '=', $paymentType['name'])->first();

            if ($record) {
                $record->name = $paymentType['name'];
                $record->gateway_type_id = ! empty($paymentType['gateway_type_id']) ? $paymentType['gateway_type_id'] : null;

                $record->save();
            } else {
                PaymentType::create($paymentType);
            }
        }
    }
}
