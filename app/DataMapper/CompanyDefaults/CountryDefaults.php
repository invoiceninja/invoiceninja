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

namespace App\DataMapper\CompanyDefaults;

class CountryDefaults
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'currency_id'             => null,
        'timezone_id'             => null,
        'language_id'             => null,
        'e_invoice_type'          => null,
        'enabled_tax_rates'       => 0,
        'enabled_item_tax_rates'  => 1,
        'tax_rates'               => [],
        'translations'            => null,
        'custom_fields'           => null,
    ];

    /**
     * Per-country configuration keyed by country_id (ISO 3166 numeric).
     *
     * Only keys that differ from DEFAULTS need to be specified.
     * currency_id and timezone_id are static seeded IDs.
     *
     * @var array
     */
    private const DATA = [

        // ── Americas ──────────────────────────────────────────────

        '32' => [ // Argentina — ARS, America/Argentina/Buenos_Aires
            'currency_id' => '23',
            'timezone_id' => '24',
            'tax_rates' => [
                ['name' => 'IVA', 'rate' => 21],
                ['name' => 'IVA (reducido)', 'rate' => 10.5],
            ],
        ],

        '76' => [ // Brazil — BRL, America/Sao_Paulo
            'currency_id' => '20',
            'timezone_id' => '26',
            'tax_rates' => [
                ['name' => 'ICMS', 'rate' => 18],
            ],
        ],

        '124' => [ // Canada — CAD, America/Halifax
            'currency_id' => '9',
            'timezone_id' => '20',
            'tax_rates' => [
                ['name' => 'GST', 'rate' => 5],
                ['name' => 'QST', 'rate' => 9.975],
                ['name' => 'HST', 'rate' => 13],
            ],
        ],

        '152' => [ // Chile — CLP, America/Santiago
            'currency_id' => '62',
            'timezone_id' => '22',
            'tax_rates' => [
                ['name' => 'IVA', 'rate' => 19],
            ],
        ],

        '170' => [ // Colombia — COP, America/Bogota
            'currency_id' => '30',
            'timezone_id' => '17',
            'tax_rates' => [
                ['name' => 'IVA', 'rate' => 19],
                ['name' => 'IVA (reducido)', 'rate' => 5],
            ],
        ],

        '484' => [ // Mexico — MXN, America/Mexico_City
            'currency_id' => '28',
            'timezone_id' => '11',
            'tax_rates' => [
                ['name' => 'IVA', 'rate' => 16],
            ],
        ],

        '604' => [ // Peru — PEN, America/Lima
            'currency_id' => '67',
            'timezone_id' => '18',
            'tax_rates' => [
                ['name' => 'IGV', 'rate' => 18],
            ],
        ],

        '840' => [ // United States — USD, America/New_York
            'currency_id' => '1',
            'timezone_id' => '15',
            'tax_rates' => [],
        ],

        // ── Europe (EU) ──────────────────────────────────────────

        '40' => [ // Austria — EUR, Europe/Vienna
            'currency_id' => '3',
            'timezone_id' => '50',
            'tax_rates' => [
                ['name' => 'USt', 'rate' => 20],
                ['name' => 'USt (ermäßigt)', 'rate' => 10],
            ],
        ],

        '56' => [ // Belgium — EUR, Europe/Brussels
            'currency_id' => '3',
            'timezone_id' => '39',
            'tax_rates' => [
                ['name' => 'BTW', 'rate' => 21],
                ['name' => 'BTW (verlaagd)', 'rate' => 6],
            ],
        ],

        '100' => [ // Bulgaria — BGN, Europe/Sofia
            'currency_id' => '39',
            'timezone_id' => '62',
            'tax_rates' => [
                ['name' => 'ДДС', 'rate' => 20],
                ['name' => 'ДДС (намалена)', 'rate' => 9],
            ],
        ],

        '191' => [ // Croatia — EUR, Europe/Zagreb
            'currency_id' => '43',
            'timezone_id' => '52',
            'tax_rates' => [
                ['name' => 'PDV', 'rate' => 25],
                ['name' => 'PDV (sniženi)', 'rate' => 5],
            ],
        ],

        '196' => [ // Cyprus — EUR, Europe/Athens
            'currency_id' => '3',
            'timezone_id' => '53',
            'tax_rates' => [
                ['name' => 'ΦΠΑ', 'rate' => 19],
                ['name' => 'ΦΠΑ (μειωμένος)', 'rate' => 9],
            ],
        ],

        '203' => [ // Czech Republic — CZK, Europe/Prague
            'currency_id' => '51',
            'timezone_id' => '45',
            'tax_rates' => [
                ['name' => 'DPH', 'rate' => 21],
                ['name' => 'DPH (snížená)', 'rate' => 15],
            ],
        ],

        '208' => [ // Denmark — DKK, Europe/Copenhagen
            'currency_id' => '5',
            'timezone_id' => '41',
            'tax_rates' => [
                ['name' => 'moms', 'rate' => 25],
            ],
        ],

        '233' => [ // Estonia — EUR, Europe/Tallinn
            'currency_id' => '3',
            'timezone_id' => '63',
            'tax_rates' => [
                ['name' => 'KM', 'rate' => 22],
                ['name' => 'KM (vähendatud)', 'rate' => 9],
            ],
        ],

        '246' => [ // Finland — EUR, Europe/Helsinki
            'currency_id' => '3',
            'timezone_id' => '57',
            'tax_rates' => [
                ['name' => 'ALV', 'rate' => 25.5],
                ['name' => 'ALV (alennettu)', 'rate' => 14],
            ],
        ],

        '250' => [ // France — EUR, Europe/Paris
            'currency_id' => '3',
            'timezone_id' => '44',
            'tax_rates' => [
                ['name' => 'TVA', 'rate' => 20],
                ['name' => 'TVA (réduit)', 'rate' => 5.5],
            ],
        ],

        '276' => [ // Germany — EUR, Europe/Berlin
            'currency_id' => '3',
            'timezone_id' => '37',
            'tax_rates' => [
                ['name' => 'MwSt', 'rate' => 19],
                ['name' => 'MwSt (ermäßigt)', 'rate' => 7],
            ],
        ],

        '300' => [ // Greece — EUR, Europe/Athens
            'currency_id' => '3',
            'timezone_id' => '53',
            'tax_rates' => [
                ['name' => 'ΦΠΑ', 'rate' => 24],
                ['name' => 'ΦΠΑ (μειωμένος)', 'rate' => 13],
            ],
        ],

        '348' => [ // Hungary — HUF, Europe/Budapest
            'currency_id' => '69',
            'timezone_id' => '40',
            'tax_rates' => [
                ['name' => 'ÁFA', 'rate' => 27],
                ['name' => 'ÁFA (kedvezményes)', 'rate' => 5],
            ],
        ],

        '372' => [ // Ireland — EUR, Europe/Dublin
            'currency_id' => '3',
            'timezone_id' => '31',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 23],
                ['name' => 'VAT (reduced)', 'rate' => 13.5],
            ],
        ],

        '380' => [ // Italy — EUR, Europe/Rome
            'currency_id' => '3',
            'timezone_id' => '46',
            'tax_rates' => [
                ['name' => 'IVA', 'rate' => 22],
                ['name' => 'IVA (ridotta)', 'rate' => 10],
            ],
        ],

        '428' => [ // Latvia — EUR, Europe/Riga
            'currency_id' => '3',
            'timezone_id' => '61',
            'tax_rates' => [
                ['name' => 'PVN', 'rate' => 21],
                ['name' => 'PVN (samazināta)', 'rate' => 12],
            ],
        ],

        '440' => [ // Lithuania — EUR, Europe/Vilnius
            'currency_id' => '3',
            'timezone_id' => '64',
            'tax_rates' => [
                ['name' => 'PVM', 'rate' => 21],
                ['name' => 'PVM (lengvatinis)', 'rate' => 9],
            ],
        ],

        '442' => [ // Luxembourg — EUR, Europe/Brussels
            'currency_id' => '3',
            'timezone_id' => '39',
            'tax_rates' => [
                ['name' => 'TVA', 'rate' => 17],
                ['name' => 'TVA (réduit)', 'rate' => 3],
            ],
        ],

        '470' => [ // Malta — EUR, Europe/Rome
            'currency_id' => '3',
            'timezone_id' => '46',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 18],
                ['name' => 'VAT (reduced)', 'rate' => 5],
            ],
        ],

        '528' => [ // Netherlands — EUR, Europe/Amsterdam
            'currency_id' => '3',
            'timezone_id' => '35',
            'tax_rates' => [
                ['name' => 'BTW', 'rate' => 21],
                ['name' => 'BTW (laag)', 'rate' => 9],
            ],
        ],

        '616' => [ // Poland — PLN, Europe/Warsaw
            'currency_id' => '49',
            'timezone_id' => '51',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 23],
                ['name' => 'VAT (obniżony)', 'rate' => 8],
            ],
        ],

        '620' => [ // Portugal — EUR, Europe/Lisbon
            'currency_id' => '3',
            'timezone_id' => '32',
            'tax_rates' => [
                ['name' => 'IVA', 'rate' => 23],
                ['name' => 'IVA (reduzido)', 'rate' => 6],
            ],
        ],

        '642' => [ // Romania — RON, Europe/Bucharest
            'currency_id' => '42',
            'timezone_id' => '54',
            'tax_rates' => [
                ['name' => 'TVA', 'rate' => 19],
                ['name' => 'TVA (redusă)', 'rate' => 5],
            ],
        ],

        '703' => [ // Slovakia — EUR, Europe/Bratislava
            'currency_id' => '3',
            'timezone_id' => '38',
            'tax_rates' => [
                ['name' => 'DPH', 'rate' => 20],
                ['name' => 'DPH (znížená)', 'rate' => 10],
            ],
        ],

        '705' => [ // Slovenia — EUR, Europe/Ljubljana
            'currency_id' => '3',
            'timezone_id' => '42',
            'tax_rates' => [
                ['name' => 'DDV', 'rate' => 22],
                ['name' => 'DDV (znižana)', 'rate' => 9.5],
            ],
        ],

        '724' => [ // Spain — EUR, Europe/Madrid
            'currency_id' => '3',
            'timezone_id' => '43',
            'language_id' => '7',
            'e_invoice_type' => 'Facturae_3.2.2',
            'enabled_item_tax_rates' => 2,
            'tax_rates' => [
                ['name' => 'IVA', 'rate' => 21],
                ['name' => 'IVA (reducido)', 'rate' => 10],
                ['name' => 'IRPF', 'rate' => -15],
            ],
            'custom_fields' => [
                'contact1' => 'Rol|CONTABLE,FISCAL,GESTOR,RECEPTOR,TRAMITADOR,PAGADOR,PROPONENTE,B2B_FISCAL,B2B_PAYER,B2B_BUYER,B2B_COLLECTOR,B2B_SELLER,B2B_PAYMENT_RECEIVER,B2B_COLLECTION_RECEIVER,B2B_ISSUER',
                'contact2' => 'Code|single_line_text',
                'contact3' => 'Nombre|single_line_text',
                'client1' => 'Administración Pública|switch',
            ],
        ],

        '752' => [ // Sweden — SEK, Europe/Stockholm
            'currency_id' => '7',
            'timezone_id' => '49',
            'tax_rates' => [
                ['name' => 'moms', 'rate' => 25],
                ['name' => 'moms (reducerad)', 'rate' => 12],
            ],
        ],

        // ── Europe (Non-EU) ──────────────────────────────────────

        '352' => [ // Iceland — ISK, Europe/London
            'currency_id' => '63',
            'timezone_id' => '33',
            'tax_rates' => [
                ['name' => 'VSK', 'rate' => 24],
                ['name' => 'VSK (lægra)', 'rate' => 11],
            ],
        ],

        '578' => [ // Norway — NOK, Europe/Berlin
            'currency_id' => '14',
            'timezone_id' => '37',
            'tax_rates' => [
                ['name' => 'mva', 'rate' => 25],
                ['name' => 'mva (redusert)', 'rate' => 12],
            ],
        ],

        '756' => [ // Switzerland — CHF, Europe/Berlin
            'currency_id' => '17',
            'timezone_id' => '37',
            'tax_rates' => [
                ['name' => 'MWST', 'rate' => 8.1],
                ['name' => 'MWST (reduziert)', 'rate' => 2.6],
            ],
        ],

        '792' => [ // Turkey — TRY, Europe/Istanbul
            'currency_id' => '41',
            'timezone_id' => '65',
            'tax_rates' => [
                ['name' => 'KDV', 'rate' => 20],
                ['name' => 'KDV (indirimli)', 'rate' => 10],
            ],
        ],

        '826' => [ // United Kingdom — GBP, Europe/London
            'currency_id' => '2',
            'timezone_id' => '33',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 20],
                ['name' => 'VAT (reduced)', 'rate' => 5],
            ],
        ],

        // ── Asia & Middle East ───────────────────────────────────

        '356' => [ // India — INR, Asia/Kolkata
            'currency_id' => '11',
            'timezone_id' => '80',
            'tax_rates' => [
                ['name' => 'GST', 'rate' => 18],
                ['name' => 'GST (reduced)', 'rate' => 5],
            ],
        ],

        '360' => [ // Indonesia — IDR, Asia/Jakarta
            'currency_id' => '27',
            'timezone_id' => '88',
            'tax_rates' => [
                ['name' => 'PPN', 'rate' => 11],
            ],
        ],

        '376' => [ // Israel — ILS, Asia/Jerusalem
            'currency_id' => '6',
            'timezone_id' => '58',
            'tax_rates' => [
                ['name' => 'מע"מ', 'rate' => 17],
            ],
        ],

        '392' => [ // Japan — JPY, Asia/Tokyo
            'currency_id' => '45',
            'timezone_id' => '100',
            'tax_rates' => [
                ['name' => '消費税', 'rate' => 10],
                ['name' => '消費税 (軽減)', 'rate' => 8],
            ],
        ],

        '410' => [ // South Korea — KRW, Asia/Seoul
            'currency_id' => '79',
            'timezone_id' => '99',
            'tax_rates' => [
                ['name' => '부가가치세', 'rate' => 10],
            ],
        ],

        '458' => [ // Malaysia — MYR, Asia/Kuala_Lumpur
            'currency_id' => '19',
            'timezone_id' => '92',
            'tax_rates' => [
                ['name' => 'SST', 'rate' => 10],
                ['name' => 'SST (services)', 'rate' => 8],
            ],
        ],

        '608' => [ // Philippines — PHP, Asia/Singapore
            'currency_id' => '10',
            'timezone_id' => '94',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 12],
            ],
        ],

        '682' => [ // Saudi Arabia — SAR, Asia/Riyadh
            'currency_id' => '44',
            'timezone_id' => '69',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 15],
            ],
        ],

        '702' => [ // Singapore — SGD, Asia/Singapore
            'currency_id' => '13',
            'timezone_id' => '94',
            'tax_rates' => [
                ['name' => 'GST', 'rate' => 9],
            ],
        ],

        '764' => [ // Thailand — THB, Asia/Bangkok
            'currency_id' => '21',
            'timezone_id' => '86',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 7],
            ],
        ],

        '784' => [ // United Arab Emirates — AED, Asia/Dubai
            'currency_id' => '25',
            'timezone_id' => '115',
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 5],
            ],
        ],

        // ── Oceania ──────────────────────────────────────────────

        '36' => [ // Australia — AUD, Australia/Sydney
            'currency_id' => '12',
            'timezone_id' => '105',
            'enabled_tax_rates' => 1,
            'enabled_item_tax_rates' => 1,
            'tax_rates' => [
                ['name' => 'GST', 'rate' => 10],
            ],
            'translations' => [
                'invoice' => 'Tax Invoice',
            ],
        ],

        '554' => [ // New Zealand — NZD, Pacific/Auckland
            'currency_id' => '15',
            'timezone_id' => '113',
            'enabled_tax_rates' => 1,
            'tax_rates' => [
                ['name' => 'GST', 'rate' => 15],
            ],
        ],

        // ── Africa ───────────────────────────────────────────────

        '710' => [ // South Africa — ZAR, Africa/Harare
            'currency_id' => '4',
            'timezone_id' => '56',
            'enabled_tax_rates' => 1,
            'enabled_item_tax_rates' => 1,
            'tax_rates' => [
                ['name' => 'VAT', 'rate' => 15],
            ],
            'translations' => [
                'invoice' => 'Tax Invoice',
            ],
        ],
    ];

    /**
     * Get the configuration for a given country, merged with defaults.
     *
     * @param  string $countryId
     * @return array<string, mixed>
     */
    public static function get(string $countryId): array
    {
        return array_merge(self::DEFAULTS, self::DATA[$countryId] ?? []);
    }

    /**
     * Check if a country has specific configuration.
     *
     * @param  string $countryId
     * @return bool
     */
    public static function has(string $countryId): bool
    {
        return isset(self::DATA[$countryId]);
    }

    /**
     * Get all configured country IDs.
     *
     * @return array<string>
     */
    public static function countries(): array
    {
        return array_map('strval', array_keys(self::DATA));
    }
}
