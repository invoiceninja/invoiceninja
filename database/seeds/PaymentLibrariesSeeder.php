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

        $this->createGateways();
        $this->createPaymentTerms();
        $this->createDateFormats();
        $this->createDatetimeFormats();
        $this->createInvoiceDesigns();
        $this->updateLocalization();
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
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'South African Rand', 'code' => 'ZAR', 'symbol' => 'R', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Danish Krone', 'code' => 'DKK', 'symbol' => 'kr ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Israeli Shekel', 'code' => 'ILS', 'symbol' => 'NIS ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Swedish Krona', 'code' => 'SEK', 'symbol' => 'kr ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Kenyan Shilling', 'code' => 'KES', 'symbol' => 'KSh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Philippine Peso', 'code' => 'PHP', 'symbol' => 'P ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => 'Rs. ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Singapore Dollar', 'code' => 'SGD', 'symbol' => 'SGD ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Norske Kroner', 'code' => 'NOK', 'symbol' => 'kr ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'New Zealand Dollar', 'code' => 'NZD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Vietnamese Dong', 'code' => 'VND', 'symbol' => 'VND ', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => 'CHF ', 'precision' => '2', 'thousand_separator' => '\'', 'decimal_separator' => '.'],
            ['name' => 'Guatemalan Quetzal', 'code' => 'GTQ', 'symbol' => 'Q', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Malaysian Ringgit', 'code' => 'MYR', 'symbol' => 'RM', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Brazilian Real', 'code' => 'BRL', 'symbol' => 'R$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Thai Baht', 'code' => 'THB', 'symbol' => 'THB ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Nigerian Naira', 'code' => 'NGN', 'symbol' => 'NGN ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Argentine Peso', 'code' => 'ARS', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => 'Tk', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'United Arab Emirates Dirham', 'code' => 'AED', 'symbol' => 'DH ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Hong Kong Dollar', 'code' => 'HKD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Indonesian Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Mexican Peso', 'code' => 'MXN', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Egyptian Pound', 'code' => 'EGP', 'symbol' => 'E£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Colombian Peso', 'code' => 'COP', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'West African Franc', 'code' => 'XOF', 'symbol' => 'CFA ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Chinese Renminbi', 'code' => 'CNY', 'symbol' => 'RMB ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Rwandan Franc', 'code' => 'RWF', 'symbol' => 'RF ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Tanzanian Shilling', 'code' => 'TZS', 'symbol' => 'TSh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Netherlands Antillean Guilder', 'code' => 'ANG', 'symbol' => 'ANG ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Trinidad and Tobago Dollar', 'code' => 'TTD', 'symbol' => 'TT$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ];

        foreach ($currencies as $currency) {
            $record = Currency::whereCode($currency['code'])->first();
            if ($record) {
                $record->name = $currency['name'];
                $record->symbol = $currency['symbol'];
                $record->thousand_separator = $currency['thousand_separator'];
                $record->decimal_separator = $currency['decimal_separator'];
                $record->save();
            } else {
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
            ['format' => 'd-m-Y', 'picker_format' => 'dd-mm-yyyy', 'label' => '20-03-2013'],
            ['format' => 'm/d/Y', 'picker_format' => 'mm/dd/yyyy', 'label' => '03/20/2013']
        ];
        
        foreach ($formats as $format) {
            $record = DateFormat::whereLabel($format['label'])->first();
            if ($record) {
                $record->format = $format['format'];
                $record->picker_format = $format['picker_format'];
                $record->save();
            } else {
                DateFormat::create($format);
            }
        }
    }

    private function createDatetimeFormats() {

        $formats = [
            [
                'format' => 'd/M/Y g:i a',
                'format_moment' => 'DD/MMM/YYYY h:mm:ss a',
                'label' => '10/Mar/2013'
            ],
            [
                'format' => 'd-M-Y g:i a',
                'format_moment' => 'DD-MMM-YYYY h:mm:ss a',
                'label' => '10-Mar-2013'
            ],
            [
                'format' => 'd/F/Y g:i a',
                'format_moment' => 'DD/MMMM/YYYY h:mm:ss a',
                'label' => '10/March/2013'
            ],
            [
                'format' => 'd-F-Y g:i a',
                'format_moment' => 'DD-MMMM-YYYY h:mm:ss a',
                'label' => '10-March-2013'
            ],
            [
                'format' => 'M j, Y g:i a',
                'format_moment' => 'MMM D, YYYY h:mm:ss a',
                'label' => 'Mar 10, 2013 6:15 pm'
            ],
            [
                'format' => 'F j, Y g:i a',
                'format_moment' => 'MMMM D, YYYY h:mm:ss a',
                'label' => 'March 10, 2013 6:15 pm'
            ],
            [
                'format' => 'D M jS, Y g:i a',
                'format_moment' => 'ddd MMM Do, YYYY h:mm:ss a',
                'label' => 'Mon March 10th, 2013 6:15 pm'
            ],
            [
                'format' => 'Y-M-d g:i a',
                'format_moment' => 'YYYY-MMM-DD h:mm:ss a',
                'label' => '2013-03-10 6:15 pm'
            ],
            [
                'format' => 'd-m-Y g:i a',
                'format_moment' => 'DD-MM-YYYY h:mm:ss a',
                'label' => '20-03-2013 6:15 pm'
            ],
            [
                'format' => 'm/d/Y g:i a',
                'format_moment' => 'MM/DD/YYYY h:mm:ss a',
                'label' => '03/20/2013 6:15 pm'
            ]
        ];
        
        foreach ($formats as $format) {
            $record = DatetimeFormat::whereLabel($format['label'])->first();
            if ($record) {
                $record->format = $format['format'];
                $record->format_moment = $format['format_moment'];
                $record->save();
            } else {
                DatetimeFormat::create($format);
            }
        }
    }

    private function createInvoiceDesigns() {
        $designs = [
            'Clean',
            'Bold',
            'Modern',
            'Plain',
            'Business',
            'Creative',
            'Elegant',
            'Hipster',
            'Playful',
            'Photo',
        ];
        
        for ($i=0; $i<count($designs); $i++) {
            $design = $designs[$i];
            $fileName = storage_path() . '/templates/' . strtolower($design) . '.js';
            if (file_exists($fileName)) {
                $pdfmake = file_get_contents($fileName);
                if ($pdfmake) {
                    $record = InvoiceDesign::whereName($design)->first();
                    if (!$record) {
                        $record = new InvoiceDesign;
                        $record->id = $i + 1;
                        $record->name = $design;
                    }
                    $record->pdfmake = $pdfmake;
                    $record->save();
                }
            }
        }
    }

    private function updateLocalization() {
        // Source: http://www.bitboost.com/ref/international-address-formats.html
        // Source: https://en.wikipedia.org/wiki/Linguistic_issues_concerning_the_euro
        $countries = [
            'AR' => [
                'swap_postal_code' => true,
            ],
            'AT' => [ // Austria
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'BE' => [
                'swap_postal_code' => true,
            ],
            'BG' => [ // Belgium
                'swap_currency_symbol' => true,
            ],
            'CH' => [
                'swap_postal_code' => true,
            ],
            'CZ' => [ // Czech Republic
                'swap_currency_symbol' => true,
            ],
            'DE' => [ // Germany
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'DK' => [
                'swap_postal_code' => true,
            ],
            'EE' => [ // Estonia
                'swap_currency_symbol' => true,
            ],
            'ES' => [ // Spain
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'FI' => [ // Finland
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'FR' => [ // France
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'GR' => [ // Greece
                'swap_currency_symbol' => true,
            ],
            'HR' => [ // Croatia
                'swap_currency_symbol' => true,
            ],
            'HU' => [ // Hungary
                'swap_currency_symbol' => true,
            ],
            'GL' => [
                'swap_postal_code' => true,
            ],
            'IE' => [ // Ireland
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            'IL' => [
                'swap_postal_code' => true,
            ],
            'IS' => [ // Iceland
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'IT' => [ // Italy
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'LT' => [ // Lithuania
                'swap_currency_symbol' => true,
            ],
            'LU' => [
                'swap_postal_code' => true,
            ],
            'MY' => [
                'swap_postal_code' => true,
            ],
            'MX' => [
                'swap_postal_code' => true,
            ],
            'NL' => [
                'swap_postal_code' => true,
            ],
            'PL' => [ // Poland
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'PT' => [ // Portugal
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'RO' => [ // Romania
                'swap_currency_symbol' => true,
            ],
            'SE' => [ // Sweden
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'SI' => [ // Slovenia
                'swap_currency_symbol' => true,
            ],
            'SK' => [ // Slovakia
                'swap_currency_symbol' => true,
            ],
            'UY' => [
                'swap_postal_code' => true,
            ],
        ];

        foreach ($countries as $code => $data) {
            $country = Country::where('iso_3166_2', '=', $code)->first();
            if (isset($data['swap_postal_code'])) {
                $country->swap_postal_code = true;
            }
            if (isset($data['swap_currency_symbol'])) {
                $country->swap_currency_symbol = true;
            }
            if (isset($data['thousand_separator'])) {
                $country->thousand_separator = $data['thousand_separator'];
            }
            if (isset($data['decimal_separator'])) {
                $country->decimal_separator = $data['decimal_separator'];
            }
            $country->save();
        }
    }

}
