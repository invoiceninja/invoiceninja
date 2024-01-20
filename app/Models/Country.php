<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

/**
 * App\Models\Country
 *
 * @property int $id
 * @property string|null $capital
 * @property string|null $citizenship
 * @property string|null $country_code
 * @property string|null $currency
 * @property string|null $currency_code
 * @property string|null $currency_sub_unit
 * @property string|null $full_name
 * @property string|null $iso_3166_2
 * @property string|null $iso_3166_3
 * @property string|null $name
 * @property string|null $region_code
 * @property string|null $sub_region_code
 * @property bool $eea
 * @property bool $swap_postal_code
 * @property bool $swap_currency_symbol
 * @property string|null $thousand_separator
 * @property string|null $decimal_separator
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Country query()
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereCapital($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereCitizenship($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereCurrencySubUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereDecimalSeparator($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereEea($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereIso31662($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereIso31663($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereRegionCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereSubRegionCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereSwapCurrencySymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereSwapPostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Country whereThousandSeparator($value)
 * @mixin \Eloquent
 */
class Country extends StaticModel
{
    public $timestamps = false;

    protected $casts = [
        'eea' => 'boolean',
        'swap_postal_code' => 'boolean',
        'swap_currency_symbol' => 'boolean',
        'thousand_separator' => 'string',
        'decimal_separator' => 'string',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    /**
     * Localizes the country name for the clients language.
     *
     * @return string The translated country name
     */
    public function getName(): string
    {
        return trans('texts.country_'.$this->name);
    }
    public function getID(): string
    {
        return $this->id;
    }
}
