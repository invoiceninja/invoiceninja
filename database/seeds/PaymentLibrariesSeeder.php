<?php

use App\Models\Gateway;
use App\Models\PaymentTerm;
use App\Models\Currency;

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
            ['name' => 'Neteller', 'provider' => 'Neteller', 'payment_library_id' => 1],
            ['name' => 'Pacnet', 'provider' => 'Pacnet', 'payment_library_id' => 1],
            ['name' => 'PaymentSense', 'provider' => 'PaymentSense', 'payment_library_id' => 1],
            ['name' => 'Realex', 'provider' => 'Realex_Remote', 'payment_library_id' => 1],
            ['name' => 'Sisow', 'provider' => 'Sisow', 'payment_library_id' => 1],
            ['name' => 'Skrill', 'provider' => 'Skrill', 'payment_library_id' => 1],
            ['name' => 'BitPay', 'provider' => 'BitPay', 'payment_library_id' => 1],
            ['name' => 'Dwolla', 'provider' => 'Dwolla', 'payment_library_id' => 1],
        ];

        foreach ($gateways as $gateway) {
            if (!DB::table('gateways')->where('name', '=', $gateway['name'])->get()) {
                Gateway::create($gateway);
            }
        }

        $paymentTerms = [
            ['num_days' => -1, 'name' => 'Net 0'],
        ];

        foreach ($paymentTerms as $paymentTerm) {
            if (!DB::table('payment_terms')->where('name', '=', $paymentTerm['name'])->get()) {
                PaymentTerm::create($paymentTerm);
            }
        }

        $currencies = [
            ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Pound Sterling', 'code' => 'GBP', 'symbol' => '£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Rand', 'code' => 'ZAR', 'symbol' => 'R', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Danish Krone', 'code' => 'DKK', 'symbol' => 'kr ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Israeli Shekel', 'code' => 'ILS', 'symbol' => 'NIS ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Swedish Krona', 'code' => 'SEK', 'symbol' => 'kr ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Kenyan Shilling', 'code' => 'KES', 'symbol' => 'KSh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Philippine Peso', 'code' => 'PHP', 'symbol' => 'P ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => 'Rs. ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Singapore Dollar', 'code' => 'SGD', 'symbol' => 'SGD ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Norske Kroner', 'code' => 'NOK', 'symbol' => 'kr ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'New Zealand Dollar', 'code' => 'NZD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Vietnamese Dong', 'code' => 'VND', 'symbol' => 'VND ', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => 'CHF ', 'precision' => '2', 'thousand_separator' => '\'', 'decimal_separator' => '.'],
            ['name' => 'Guatemalan Quetzal', 'code' => 'GTQ', 'symbol' => 'Q', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Malaysian Ringgit', 'code' => 'MYR', 'symbol' => 'RM', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Brazilian Real', 'code' => 'BRL', 'symbol' => 'R$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ];

        foreach ($currencies as $currency) {
            if (!DB::table('currencies')->whereName($currency['name'])->get()) {
                Currency::create($currency);
            }
        }
    }
}
