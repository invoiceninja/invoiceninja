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

namespace App\Services\EDocument\Standards\Peppol;

class CountryFactory
{
    /**
     * Map of ISO 3166-2 country codes to handler classes.
     *
     * Countries without explicit handlers will use the BaseCountry (no-op) handler.
     * To add a new country, create a handler class extending BaseCountry
     * and add the mapping here.
     */
    private static array $handlers = [
        'AD' => AD::class,
        'AT' => AT::class,
        'AU' => AU::class,
        'CH' => CH::class,
        'DE' => DE::class,
        'DK' => DK::class,
        'ES' => ES::class,
        'FI' => FI::class,
        'FR' => FR::class,
        'IN' => IN::class,
        'IT' => IT::class,
        'MY' => MY::class,
        'NL' => NL::class,
        'NZ' => NZ::class,
        'PL' => PL::class,
        'RO' => RO::class,
        'SE' => SE::class,
        'SG' => SG::class,
    ];

    /**
     * Create a country handler instance for the given ISO 3166-2 code.
     *
     * Returns a no-op BaseCountry for unsupported country codes,
     * so new countries can be added without touching the dispatcher.
     */
    public static function make(string $countryCode): CountryHandler
    {
        $class = self::$handlers[$countryCode] ?? BaseCountry::class;
        return new $class();
    }

    /**
     * Check if a specific handler exists for a country code.
     */
    public static function has(string $countryCode): bool
    {
        return isset(self::$handlers[$countryCode]);
    }
}
