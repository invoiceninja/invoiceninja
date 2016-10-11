<?php

use App\Models\PaymentType;

class PaymentTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $paymentTypes = [
            array('name' => 'Apply Credit'),
            array('name' => 'Bank Transfer', 'gateway_type_id' => GATEWAY_TYPE_BANK_TRANSFER),
            array('name' => 'Cash'),
            array('name' => 'Debit', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'ACH', 'gateway_type_id' => GATEWAY_TYPE_BANK_TRANSFER),
            array('name' => 'Visa Card', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'MasterCard', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'American Express', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Discover Card', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Diners Card', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'EuroCard', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Nova', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Credit Card Other', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'PayPal', 'gateway_type_id' => GATEWAY_TYPE_PAYPAL),
            array('name' => 'Google Wallet'),
            array('name' => 'Check'),
            array('name' => 'Carte Blanche', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'UnionPay', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'JCB', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Laser', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Maestro', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Solo', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
            array('name' => 'Switch', 'gateway_type_id' => GATEWAY_TYPE_CREDIT_CARD),
        ];

        foreach ($paymentTypes as $paymentType) {
            $record = PaymentType::where('name', '=', $paymentType['name'])->first();

            if ( $record) {
                $record->name = $paymentType['name'];
                $record->gateway_type_id = ! empty($paymentType['gateway_type_id']) ? $paymentType['gateway_type_id'] : null;

                $record->save();
            } else {
                PaymentType::create($paymentType);
            }
        }
    }

}