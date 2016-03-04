<?php

use App\Models\Gateway;
use App\Models\PaymentTerm;
use App\Models\Currency;
use App\Models\DateFormat;
use App\Models\DatetimeFormat;
use App\Models\InvoiceDesign;
use App\Models\Country;

class PaymentLibrariesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $gateways = [
            ['name' => 'BeanStream', 'provider' => 'BeanStream', 'payment_library_id' => 2],
            ['name' => 'Psigate', 'provider' => 'Psigate', 'payment_library_id' => 2],
            ['name' => 'moolah', 'provider' => 'AuthorizeNet_AIM', 'sort_order' => 1, 'recommended' => 1, 'site_url' => 'https://invoiceninja.mymoolah.com/', 'payment_library_id' => 1],
            ['name' => 'Alipay', 'provider' => 'Alipay_Express', 'payment_library_id' => 1],
            ['name' => 'Buckaroo', 'provider' => 'Buckaroo_CreditCard', 'payment_library_id' => 1],
            ['name' => 'Coinbase', 'provider' => 'Coinbase', 'payment_library_id' => 1],
            ['name' => 'DataCash', 'provider' => 'DataCash', 'payment_library_id' => 1],
            ['name' => 'Neteller', 'provider' => 'Neteller', 'payment_library_id' => 2],
            ['name' => 'Pacnet', 'provider' => 'Pacnet', 'payment_library_id' => 1],
            ['name' => 'PaymentSense', 'provider' => 'PaymentSense', 'payment_library_id' => 1],
            ['name' => 'Realex', 'provider' => 'Realex_Remote', 'payment_library_id' => 1],
            ['name' => 'Sisow', 'provider' => 'Sisow', 'payment_library_id' => 1],
            ['name' => 'Skrill', 'provider' => 'Skrill', 'payment_library_id' => 1],
            ['name' => 'BitPay', 'provider' => 'BitPay', 'payment_library_id' => 1],
            ['name' => 'Dwolla', 'provider' => 'Dwolla', 'payment_library_id' => 1],
            ['name' => 'Eway Rapid', 'provider' => 'Eway_RapidShared', 'payment_library_id' => 1],
            ['name' => 'AGMS', 'provider' => 'Agms', 'payment_library_id' => 1],
            ['name' => 'Barclays', 'provider' => 'BarclaysEpdq\Essential', 'payment_library_id' => 1],
            ['name' => 'Cardgate', 'provider' => 'Cardgate', 'payment_library_id' => 1],
            ['name' => 'Checkout.com', 'provider' => 'CheckoutCom', 'payment_library_id' => 1],
            ['name' => 'Creditcall', 'provider' => 'Creditcall', 'payment_library_id' => 1],
            ['name' => 'Cybersource', 'provider' => 'Cybersource', 'payment_library_id' => 1],
            ['name' => 'ecoPayz', 'provider' => 'Ecopayz', 'payment_library_id' => 1],
            ['name' => 'Fasapay', 'provider' => 'Fasapay', 'payment_library_id' => 1],
            ['name' => 'Komoju', 'provider' => 'Komoju', 'payment_library_id' => 1],
            ['name' => 'Multicards', 'provider' => 'Multicards', 'payment_library_id' => 1],
            ['name' => 'Pagar.Me', 'provider' => 'Pagarme', 'payment_library_id' => 1],
            ['name' => 'Paysafecard', 'provider' => 'Paysafecard', 'payment_library_id' => 1],
            ['name' => 'Paytrace', 'provider' => 'Paytrace_CreditCard', 'payment_library_id' => 1],
            ['name' => 'Secure Trading', 'provider' => 'SecureTrading', 'payment_library_id' => 1],
            ['name' => 'SecPay', 'provider' => 'SecPay', 'payment_library_id' => 1],
            ['name' => 'WeChat Express', 'provider' => 'WeChat_Express', 'payment_library_id' => 1],
            ['name' => 'WePay', 'provider' => 'WePay', 'payment_library_id' => 1],
        ];

        foreach ($gateways as $gateway) {
            $record = Gateway::where('name', '=', $gateway['name'])->first();
            if ($record) {
                $record->provider = $gateway['provider'];
                $record->save();
            } else {
                Gateway::create($gateway);
            }
        }

    }
}
