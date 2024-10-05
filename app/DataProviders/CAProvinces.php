<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataProviders;

final class CAProvinces
{
    /**
     * The provinces and territories of Canada
     *
     * @var array
     */
    protected static $provinces = [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland And Labrador',
        'NS' => 'Nova Scotia',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'YT' => 'Yukon'
    ];

    /**
     * Get the name of the province or territory for a given abbreviation.
     *
     * @param  string  $abbreviation
     * @return string
     */
    public static function getName($abbreviation)
    {
        return self::$provinces[$abbreviation];
    }

    /**
     * Get all provinces and territories.
     *
     * @return array
     */
    public static function get()
    {
        return self::$provinces;
    }

    /**
     * Get the abbreviation for a given province or territory name.
     *
     * @param  string  $name
     * @return string
     */
    public static function getAbbreviation($name)
    {
        return array_search(ucwords($name), self::$provinces);
    }
}
