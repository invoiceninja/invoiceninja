<?php

use App\Models\Gateway;

class PaymentLibrariesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $gateways = [
            ['name' => 'Authorize.Net AIM', 'provider' => 'AuthorizeNet_AIM', 'sort_order' => 4],
            ['name' => 'Authorize.Net SIM', 'provider' => 'AuthorizeNet_SIM', 'payment_library_id' => 2],
            ['name' => 'CardSave', 'provider' => 'CardSave'],
            ['name' => 'Eway Rapid', 'provider' => 'Eway_RapidShared', 'is_offsite' => true],
            ['name' => 'FirstData Connect', 'provider' => 'FirstData_Connect'],
            ['name' => 'GoCardless', 'provider' => 'GoCardless', 'is_offsite' => true],
            ['name' => 'Migs ThreeParty', 'provider' => 'Migs_ThreeParty'],
            ['name' => 'Migs TwoParty', 'provider' => 'Migs_TwoParty'],
            ['name' => 'Mollie', 'provider' => 'Mollie', 'is_offsite' => true, 'sort_order' => 7],
            ['name' => 'MultiSafepay', 'provider' => 'MultiSafepay'],
            ['name' => 'Netaxept', 'provider' => 'Netaxept'],
            ['name' => 'NetBanx', 'provider' => 'NetBanx'],
            ['name' => 'PayFast', 'provider' => 'PayFast', 'is_offsite' => true],
            ['name' => 'Payflow Pro', 'provider' => 'Payflow_Pro'],
            ['name' => 'PaymentExpress PxPay', 'provider' => 'PaymentExpress_PxPay'],
            ['name' => 'PaymentExpress PxPost', 'provider' => 'PaymentExpress_PxPost'],
            ['name' => 'PayPal Express', 'provider' => 'PayPal_Express', 'is_offsite' => true, 'sort_order' => 3],
            ['name' => 'PayPal Pro', 'provider' => 'PayPal_Pro'],
            ['name' => 'Pin', 'provider' => 'Pin'],
            ['name' => 'SagePay Direct', 'provider' => 'SagePay_Direct'],
            ['name' => 'SagePay Server', 'provider' => 'SagePay_Server'],
            ['name' => 'SecurePay DirectPost', 'provider' => 'SecurePay_DirectPost'],
            ['name' => 'Stripe', 'provider' => 'Stripe', 'sort_order' => 1],
            ['name' => 'TargetPay Direct eBanking', 'provider' => 'TargetPay_Directebanking'],
            ['name' => 'TargetPay Ideal', 'provider' => 'TargetPay_Ideal'],
            ['name' => 'TargetPay Mr Cash', 'provider' => 'TargetPay_Mrcash'],
            ['name' => 'TwoCheckout', 'provider' => 'TwoCheckout', 'is_offsite' => true],
            ['name' => 'WorldPay', 'provider' => 'WorldPay'],
            ['name' => 'BeanStream', 'provider' => 'BeanStream', 'payment_library_id' => 2],
            ['name' => 'Psigate', 'provider' => 'Psigate', 'payment_library_id' => 2],
            ['name' => 'moolah', 'provider' => 'AuthorizeNet_AIM'],
            ['name' => 'Alipay', 'provider' => 'Alipay_Express'],
            ['name' => 'Buckaroo', 'provider' => 'Buckaroo_CreditCard'],
            ['name' => 'Coinbase', 'provider' => 'Coinbase'],
            ['name' => 'DataCash', 'provider' => 'DataCash'],
            ['name' => 'Neteller', 'provider' => 'Neteller', 'payment_library_id' => 2],
            ['name' => 'Pacnet', 'provider' => 'Pacnet'],
            ['name' => 'PaymentSense', 'provider' => 'PaymentSense', 'payment_library_id' => 2],
            ['name' => 'Realex', 'provider' => 'Realex_Remote'],
            ['name' => 'Sisow', 'provider' => 'Sisow'],
            ['name' => 'Skrill', 'provider' => 'Skrill', 'is_offsite' => true],
            ['name' => 'BitPay', 'provider' => 'BitPay', 'is_offsite' => true, 'sort_order' => 6],
            ['name' => 'Dwolla', 'provider' => 'Dwolla', 'is_offsite' => true, 'sort_order' => 5],
            ['name' => 'AGMS', 'provider' => 'Agms'],
            ['name' => 'Barclays', 'provider' => 'BarclaysEpdq\Essential'],
            ['name' => 'Cardgate', 'provider' => 'Cardgate'],
            ['name' => 'Checkout.com', 'provider' => 'CheckoutCom'],
            ['name' => 'Creditcall', 'provider' => 'Creditcall'],
            ['name' => 'Cybersource', 'provider' => 'Cybersource'],
            ['name' => 'ecoPayz', 'provider' => 'Ecopayz'],
            ['name' => 'Fasapay', 'provider' => 'Fasapay'],
            ['name' => 'Komoju', 'provider' => 'Komoju'],
            ['name' => 'Multicards', 'provider' => 'Multicards'],
            ['name' => 'Pagar.Me', 'provider' => 'Pagarme', 'payment_library_id' => 2],
            ['name' => 'Paysafecard', 'provider' => 'Paysafecard'],
            ['name' => 'Paytrace', 'provider' => 'Paytrace_CreditCard'],
            ['name' => 'Secure Trading', 'provider' => 'SecureTrading'],
            ['name' => 'SecPay', 'provider' => 'SecPay'],
            ['name' => 'WeChat Express', 'provider' => 'WeChat_Express', 'payment_library_id' => 2],
            ['name' => 'WePay', 'provider' => 'WePay', 'is_offsite' => false],
            ['name' => 'Braintree', 'provider' => 'Braintree', 'sort_order' => 2],
            ['name' => 'Custom', 'provider' => 'Custom', 'is_offsite' => true, 'sort_order' => 8],
        ];

        foreach ($gateways as $gateway) {
            $record = Gateway::where('name', '=', $gateway['name'])->first();
            if ($record) {
                $record->fill($gateway);
                $record->save();
            } else {
                Gateway::create($gateway);
            }
        }
    }
}
