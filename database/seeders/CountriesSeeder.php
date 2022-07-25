<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Webpatser\Countries\Countries;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $countries_object = new Countries;
        $countries = $countries_object->getList();

        foreach ($countries as $countryId => $country) {
            if ($record = Country::whereCountryCode($country['country-code'])->first()) {
                $record->name = $country['name'];
                $record->full_name = ((isset($country['full_name'])) ? $country['full_name'] : null);
                $record->save();
            } else {
                \Illuminate\Support\Facades\DB::table('countries')->insert([
                    'id' => $countryId,
                    'capital' => ((isset($country['capital'])) ? $country['capital'] : null),
                    'citizenship' => ((isset($country['citizenship'])) ? $country['citizenship'] : null),
                    'country_code' => $country['country-code'],
                    'currency' => ((isset($country['currency'])) ? $country['currency'] : null),
                    'currency_code' => ((isset($country['currency_code'])) ? $country['currency_code'] : null),
                    'currency_sub_unit' => ((isset($country['currency_sub_unit'])) ? $country['currency_sub_unit'] : null),
                    'full_name' => ((isset($country['full_name'])) ? $country['full_name'] : null),
                    'iso_3166_2' => $country['iso_3166_2'],
                    'iso_3166_3' => $country['iso_3166_3'],
                    'name' => $country['name'],
                    'region_code' => $country['region-code'],
                    'sub_region_code' => $country['sub-region-code'],
                    'eea' => (bool) $country['eea'],
                    'thousand_separator' => '',
                    'decimal_separator' => '',
                ]);
            }
        }

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
            'CA' => [
                'thousand_separator' => ',',
                'decimal_separator' => '.',
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
                'thousand_separator' => ' ',
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
                'thousand_separator' => ' ',
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
            'JP' => [ // Japan
                'swap_postal_code' => true,
                'swap_currency_symbol' => true,
            ],
            'LT' => [ // Lithuania
                'swap_currency_symbol' => true,
            ],
            'LU' => [
                'swap_postal_code' => true,
            ],
            'MT' => [
                'thousand_separator' => ',',
                'decimal_separator' => '.',
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
            'US' => [
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ],
            'SR' => [ // Suriname
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
                $country->thousand_separator = $data['thousand_separator'] ?: '';
            }
            if (isset($data['decimal_separator'])) {
                $country->decimal_separator = $data['decimal_separator'] ?: '';
            }
            $country->save();
        }

        $p = Country::where('country_code', 275)->first();
        $p->name = 'Palestine';
        $p->save();
    }
}
