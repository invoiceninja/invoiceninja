<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
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
        'YT' => 'Yukon',
    ];

    /**
     * The French names of the provinces and territories of Canada, keyed by code.
     *
     * @var array
     */
    protected static $frenchProvinces = [
        'AB' => 'Alberta',
        'BC' => 'Colombie-Britannique',
        'MB' => 'Manitoba',
        'NB' => 'Nouveau-Brunswick',
        'NL' => 'Terre-Neuve-et-Labrador',
        'NS' => 'Nouvelle-Écosse',
        'ON' => 'Ontario',
        'PE' => 'Île-du-Prince-Édouard',
        'QC' => 'Québec',
        'SK' => 'Saskatchewan',
        'NT' => 'Territoires du Nord-Ouest',
        'NU' => 'Nunavut',
        'YT' => 'Yukon',
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
     * Resolve a province/territory to its two letter code.
     *
     * Accepts an existing code (e.g. "NB", "nb"), an English name
     * (e.g. "New Brunswick") or a French name (e.g. "Nouveau-Brunswick").
     *
     * @param  string|null  $name
     * @return string  The two letter code, or an empty string when no match is found.
     */
    public static function getAbbreviation($name)
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        if (isset(self::$provinces[strtoupper($name)])) {
            return strtoupper($name);
        }

        $needle = self::normalize($name);

        foreach ([self::$provinces, self::$frenchProvinces] as $set) {
            foreach ($set as $code => $province) {
                if (self::normalize($province) === $needle) {
                    return $code;
                }
            }
        }

        return '';
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
