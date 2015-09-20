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
        $this->updateSwapPostalCode();
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
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'South African Rand', 'code' => 'ZAR', 'symbol' => 'R', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
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
            ['name' => 'Nigerian Naira', 'code' => 'NGN', 'symbol' => 'NGN ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Argentine Peso', 'code' => 'ARS', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => 'Tk', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ];

        foreach ($currencies as $currency) {
            $record = Currency::whereCode($currency['code'])->first();
            if ($record) {
                $record->name = $currency['name'];
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
                'format' => 'd-M-Yk g:i a',
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
                'format' => 'm/d/Y g:i',
                'format_moment' => 'MM/DD/YYYY h:mm:ss',
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

    private function updateSwapPostalCode() {
        // Source: http://www.bitboost.com/ref/international-address-formats.html
        $countries = [
            'AR',
            'AT',
            'CH',
            'BE',
            'DE',
            'DK',
            'ES',
            'FI',
            'FR',
            'GL',
            'IL',
            'IS',
            'IT',
            'LU',
            'MY',
            'MX',
            'NL',
            'PL',
            'PT',
            'SE',
            'UY',
        ];

        for ($i=0; $i<count($countries); $i++) {
            $code = $countries[$i];
            $country = Country::where('iso_3166_2', '=', $code)->first();
            $country->swap_postal_code = true;
            $country->save();
        }
    }

}
