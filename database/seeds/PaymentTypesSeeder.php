<?php

use App\Models\PaymentType;

class PaymentTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $paymentTypes = [
            array('name' => 'Apply Credit'),
            array('name' => 'Bank Transfer'),
            array('name' => 'Cash'),
            array('name' => 'Debit'),
            array('name' => 'ACH'),
            array('name' => 'Visa Card'),
            array('name' => 'MasterCard'),
            array('name' => 'American Express'),
            array('name' => 'Discover Card'),
            array('name' => 'Diners Card'),
            array('name' => 'EuroCard'),
            array('name' => 'Nova'),
            array('name' => 'Credit Card Other'),
            array('name' => 'PayPal'),
            array('name' => 'Google Wallet'),
            array('name' => 'Check'),
            array('name' => 'Carte Blanche'),
            array('name' => 'UnionPay'),
            array('name' => 'JCB'),
            array('name' => 'Laser'),
            array('name' => 'Maestro'),
            array('name' => 'Solo'),
            array('name' => 'Switch'),
        ];

        foreach ($paymentTypes as $paymentType) {
            if (!DB::table('payment_types')->where('name', '=', $paymentType['name'])->get()) {
                PaymentType::create($paymentType);
            }
        }
    }

}