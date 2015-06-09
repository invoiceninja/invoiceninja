<?php

use App\Models\Gateway;
use App\Models\PaymentTerm;
use App\Models\Currency;
use App\Models\DateFormat;
use App\Models\DatetimeFormat;

class PaymentLibrariesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $this->createGateways();
        $this->createPaymentTerms();
        $this->createDateFormats();
        $this->createDatetimeFormats();
    }

    private function createGateways() {

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

    }

    private function createPaymentTerms() {

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
            ['name' => 'Thai baht', 'code' => 'THB', 'symbol' => 'THB ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ];

        foreach ($currencies as $currency) {
            if (!DB::table('currencies')->whereName($currency['name'])->get()) {
                Currency::create($currency);
            }
        }
    }

    private function createDateFormats() {

        $formats = [
            ['format' => 'd/M/Y', 'picker_format' => 'dd/M/yyyy', 'label' => '10/Mar/2013'],
            ['format' => 'd-M-Y', 'picker_format' => 'dd-M-yyyy', 'label' => '10-Mar-2013'],
            ['format' => 'd/F/Y', 'picker_format' => 'dd/MM/yyyy', 'label' => '10/March/2013'],
            ['format' => 'd-F-Y', 'picker_format' => 'dd-MM-yyyy', 'label' => '10-March-2013'],
            ['format' => 'M j, Y', 'picker_format' => 'M d, yyyy', 'label' => 'Mar 10, 2013'],
            ['format' => 'F j, Y', 'picker_format' => 'MM d, yyyy', 'label' => 'March 10, 2013'],
            ['format' => 'D M j, Y', 'picker_format' => 'D MM d, yyyy', 'label' => 'Mon March 10, 2013'],
            ['format' => 'Y-M-d', 'picker_format' => 'yyyy-M-dd', 'label' => '2013-03-10'],
            ['format' => 'd/m/Y', 'picker_format' => 'dd/mm/yyyy', 'label' => '20/03/2013'],
        ];
        
        foreach ($formats as $format) {
            if (!DB::table('date_formats')->whereLabel($format['label'])->get()) {
                DateFormat::create($format);
            }
        }
    }

    private function createDatetimeFormats() {

        $formats = [
            ['format' => 'd/M/Y g:i a', 'label' => '10/Mar/2013'],
            ['format' => 'd-M-Yk g:i a', 'label' => '10-Mar-2013'],
            ['format' => 'd/F/Y g:i a', 'label' => '10/March/2013'],
            ['format' => 'd-F-Y g:i a', 'label' => '10-March-2013'],
            ['format' => 'M j, Y g:i a', 'label' => 'Mar 10, 2013 6:15 pm'],
            ['format' => 'F j, Y g:i a', 'label' => 'March 10, 2013 6:15 pm'],
            ['format' => 'D M jS, Y g:ia', 'label' => 'Mon March 10th, 2013 6:15 pm'],
            ['format' => 'Y-M-d g:i a', 'label' => '2013-03-10 6:15 pm'],
            ['format' => 'd/m/Y g:i a', 'label' => '20/03/2013 6:15 pm'],
        ];
        
        foreach ($formats as $format) {
            if (!DB::table('datetime_formats')->whereLabel($format['label'])->get()) {
                DatetimeFormat::create($format);
            }
        }
    }

}
