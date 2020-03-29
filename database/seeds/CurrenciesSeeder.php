<?php

use App\Models\Currency;

class CurrenciesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        // http://www.localeplanet.com/icu/currency.html
        $currencies = [
            ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'South African Rand', 'code' => 'ZAR', 'symbol' => 'R', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Danish Krone', 'code' => 'DKK', 'symbol' => 'kr', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Israeli Shekel', 'code' => 'ILS', 'symbol' => 'NIS ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Swedish Krona', 'code' => 'SEK', 'symbol' => 'kr', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Kenyan Shilling', 'code' => 'KES', 'symbol' => 'KSh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Philippine Peso', 'code' => 'PHP', 'symbol' => 'P ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => 'Rs. ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Singapore Dollar', 'code' => 'SGD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Norske Kroner', 'code' => 'NOK', 'symbol' => 'kr', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'New Zealand Dollar', 'code' => 'NZD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Vietnamese Dong', 'code' => 'VND', 'symbol' => '', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => '', 'precision' => '2', 'thousand_separator' => '\'', 'decimal_separator' => '.'],
            ['name' => 'Guatemalan Quetzal', 'code' => 'GTQ', 'symbol' => 'Q', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Malaysian Ringgit', 'code' => 'MYR', 'symbol' => 'RM', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Brazilian Real', 'code' => 'BRL', 'symbol' => 'R$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Thai Baht', 'code' => 'THB', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Nigerian Naira', 'code' => 'NGN', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Argentine Peso', 'code' => 'ARS', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => 'Tk', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'United Arab Emirates Dirham', 'code' => 'AED', 'symbol' => 'DH ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Hong Kong Dollar', 'code' => 'HKD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Indonesian Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Mexican Peso', 'code' => 'MXN', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Egyptian Pound', 'code' => 'EGP', 'symbol' => 'E£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Colombian Peso', 'code' => 'COP', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'West African Franc', 'code' => 'XOF', 'symbol' => 'CFA ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Chinese Renminbi', 'code' => 'CNY', 'symbol' => 'RMB ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Rwandan Franc', 'code' => 'RWF', 'symbol' => 'RF ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Tanzanian Shilling', 'code' => 'TZS', 'symbol' => 'TSh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Netherlands Antillean Guilder', 'code' => 'ANG', 'symbol' => '', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Trinidad and Tobago Dollar', 'code' => 'TTD', 'symbol' => 'TT$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'East Caribbean Dollar', 'code' => 'XCD', 'symbol' => 'EC$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Ghanaian Cedi', 'code' => 'GHS', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Bulgarian Lev', 'code' => 'BGN', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => '.'],
            ['name' => 'Aruban Florin', 'code' => 'AWG', 'symbol' => 'Afl. ', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => '.'],
            ['name' => 'Turkish Lira', 'code' => 'TRY', 'symbol' => 'TL ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Romanian New Leu', 'code' => 'RON', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Croatian Kuna', 'code' => 'HRK', 'symbol' => 'kn', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Saudi Riyal', 'code' => 'SAR', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Maldivian Rufiyaa', 'code' => 'MVR', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Costa Rican Colón', 'code' => 'CRC', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Pakistani Rupee', 'code' => 'PKR', 'symbol' => 'Rs ', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Polish Zloty', 'code' => 'PLN', 'symbol' => 'zł', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Sri Lankan Rupee', 'code' => 'LKR', 'symbol' => 'LKR', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => true],
            ['name' => 'Czech Koruna', 'code' => 'CZK', 'symbol' => 'Kč', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Uruguayan Peso', 'code' => 'UYU', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Namibian Dollar', 'code' => 'NAD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Tunisian Dinar', 'code' => 'TND', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Russian Ruble', 'code' => 'RUB', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Mozambican Metical', 'code' => 'MZN', 'symbol' => 'MT', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Omani Rial', 'code' => 'OMR', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Ukrainian Hryvnia', 'code' => 'UAH', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Macanese Pataca', 'code' => 'MOP', 'symbol' => 'MOP$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Taiwan New Dollar', 'code' => 'TWD', 'symbol' => 'NT$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Dominican Peso', 'code' => 'DOP', 'symbol' => 'RD$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Chilean Peso', 'code' => 'CLP', 'symbol' => '$', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Icelandic Króna', 'code' => 'ISK', 'symbol' => 'kr', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Papua New Guinean Kina', 'code' => 'PGK', 'symbol' => 'K', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Jordanian Dinar', 'code' => 'JOD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Myanmar Kyat', 'code' => 'MMK', 'symbol' => 'K', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Peruvian Sol', 'code' => 'PEN', 'symbol' => 'S/ ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Botswana Pula', 'code' => 'BWP', 'symbol' => 'P', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Hungarian Forint', 'code' => 'HUF', 'symbol' => 'Ft', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['name' => 'Ugandan Shilling', 'code' => 'UGX', 'symbol' => 'USh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Barbadian Dollar', 'code' => 'BBD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Brunei Dollar', 'code' => 'BND', 'symbol' => 'B$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Georgian Lari', 'code' => 'GEL', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => ','],
            ['name' => 'Qatari Riyal', 'code' => 'QAR', 'symbol' => 'QR', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Honduran Lempira', 'code' => 'HNL', 'symbol' => 'L', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Surinamese Dollar', 'code' => 'SRD', 'symbol' => 'SRD', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Bahraini Dinar', 'code' => 'BHD', 'symbol' => 'BD ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Venezuelan Bolivars', 'code' => 'VES', 'symbol' => 'Bs.', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'South Korean Won', 'code' => 'KRW', 'symbol' => 'W ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Moroccan Dirham', 'code' => 'MAD', 'symbol' => 'MAD ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Jamaican Dollar', 'code' => 'JMD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Angolan Kwanza', 'code' => 'AOA', 'symbol' => 'Kz', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Haitian Gourde', 'code' => 'HTG', 'symbol' => 'G', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Zambian Kwacha', 'code' => 'ZMW', 'symbol' => 'ZK', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Nepalese Rupee', 'code' => 'NPR', 'symbol' => 'Rs. ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'CFP Franc', 'code' => 'XPF', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'], // precision should be zero
            ['name' => 'Mauritian Rupee', 'code' => 'MUR', 'symbol' => 'Rs', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Cape Verdean Escudo', 'code' => 'CVE', 'symbol' => '', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => '$'],
            ['name' => 'Kuwaiti Dinar', 'code' => 'KWD', 'symbol' => 'KD', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Algerian Dinar', 'code' => 'DZD', 'symbol' => 'DA', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Macedonian Denar', 'code' => 'MKD', 'symbol' => 'ден', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Fijian Dollar', 'code' => 'FJD', 'symbol' => 'FJ$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Bolivian Boliviano', 'code' => 'BOB', 'symbol' => 'Bs', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Albanian Lek', 'code' => 'ALL', 'symbol' => 'L ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Serbian Dinar', 'code' => 'RSD', 'symbol' => 'din', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['name' => 'Lebanese Pound', 'code' => 'LBP', 'symbol' => 'LL ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Armenian Dram', 'code' => 'AMD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Azerbaijan Manat', 'code' => 'AZN', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Bosnia and Herzegovina Convertible Mark', 'code' => 'BAM', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Belarusian Ruble', 'code' => 'BYN', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Gibraltar Pound', 'code' => 'GIP', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Moldovan Leu', 'code' => 'MDL', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Kazakhstani Tenge', 'code' => 'KZT', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['name' => 'Ethiopian Birr', 'code' => 'ETB', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ];

        foreach ($currencies as $currency) {
            $record = Currency::whereCode($currency['code'])->first();
            if ($record) {
                $record->name = $currency['name'];
                $record->symbol = $currency['symbol'];
                $record->precision = $currency['precision'];
                $record->thousand_separator = $currency['thousand_separator'];
                $record->decimal_separator = $currency['decimal_separator'];
                if (isset($currency['swap_currency_symbol'])) {
                    $record->swap_currency_symbol = $currency['swap_currency_symbol'];
                }
                $record->save();
            } else {
                Currency::create($currency);
            }
        }
    }
}
