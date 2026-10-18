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

namespace App\Import\Harvest;

class AddressParser
{
    private const CANADIAN_POSTAL_PATTERN = '/\b([A-Z]\d[A-Z])\s?(\d[A-Z]\d)\b/i';

    private const US_POSTAL_PATTERN = '/\b\d{5}(?:-\d{4})?\b/';

    /** @var array<string, string> */
    private const CANADIAN_PROVINCES = [
        'ALBERTA' => 'AB',
        'BRITISH COLUMBIA' => 'BC',
        'MANITOBA' => 'MB',
        'NEW BRUNSWICK' => 'NB',
        'NEWFOUNDLAND AND LABRADOR' => 'NL',
        'NOVA SCOTIA' => 'NS',
        'NORTHWEST TERRITORIES' => 'NT',
        'NUNAVUT' => 'NU',
        'ONTARIO' => 'ON',
        'PRINCE EDWARD ISLAND' => 'PE',
        'QUEBEC' => 'QC',
        'SASKATCHEWAN' => 'SK',
        'YUKON' => 'YT',
        'AB' => 'AB',
        'BC' => 'BC',
        'MB' => 'MB',
        'NB' => 'NB',
        'NL' => 'NL',
        'NS' => 'NS',
        'NT' => 'NT',
        'NU' => 'NU',
        'ON' => 'ON',
        'PE' => 'PE',
        'QC' => 'QC',
        'SK' => 'SK',
        'YT' => 'YT',
    ];

    /** @var array<string, string> */
    private const US_STATES = [
        'ALABAMA' => 'AL',
        'ALASKA' => 'AK',
        'ARIZONA' => 'AZ',
        'ARKANSAS' => 'AR',
        'CALIFORNIA' => 'CA',
        'COLORADO' => 'CO',
        'CONNECTICUT' => 'CT',
        'DELAWARE' => 'DE',
        'DISTRICT OF COLUMBIA' => 'DC',
        'FLORIDA' => 'FL',
        'GEORGIA' => 'GA',
        'HAWAII' => 'HI',
        'IDAHO' => 'ID',
        'ILLINOIS' => 'IL',
        'INDIANA' => 'IN',
        'IOWA' => 'IA',
        'KANSAS' => 'KS',
        'KENTUCKY' => 'KY',
        'LOUISIANA' => 'LA',
        'MAINE' => 'ME',
        'MARYLAND' => 'MD',
        'MASSACHUSETTS' => 'MA',
        'MICHIGAN' => 'MI',
        'MINNESOTA' => 'MN',
        'MISSISSIPPI' => 'MS',
        'MISSOURI' => 'MO',
        'MONTANA' => 'MT',
        'NEBRASKA' => 'NE',
        'NEVADA' => 'NV',
        'NEW HAMPSHIRE' => 'NH',
        'NEW JERSEY' => 'NJ',
        'NEW MEXICO' => 'NM',
        'NEW YORK' => 'NY',
        'NORTH CAROLINA' => 'NC',
        'NORTH DAKOTA' => 'ND',
        'OHIO' => 'OH',
        'OKLAHOMA' => 'OK',
        'OREGON' => 'OR',
        'PENNSYLVANIA' => 'PA',
        'RHODE ISLAND' => 'RI',
        'SOUTH CAROLINA' => 'SC',
        'SOUTH DAKOTA' => 'SD',
        'TENNESSEE' => 'TN',
        'TEXAS' => 'TX',
        'UTAH' => 'UT',
        'VERMONT' => 'VT',
        'VIRGINIA' => 'VA',
        'WASHINGTON' => 'WA',
        'WEST VIRGINIA' => 'WV',
        'WISCONSIN' => 'WI',
        'WYOMING' => 'WY',
        'AL' => 'AL',
        'AK' => 'AK',
        'AZ' => 'AZ',
        'AR' => 'AR',
        'CA' => 'CA',
        'CO' => 'CO',
        'CT' => 'CT',
        'DE' => 'DE',
        'DC' => 'DC',
        'FL' => 'FL',
        'GA' => 'GA',
        'HI' => 'HI',
        'ID' => 'ID',
        'IL' => 'IL',
        'IN' => 'IN',
        'IA' => 'IA',
        'KS' => 'KS',
        'KY' => 'KY',
        'LA' => 'LA',
        'ME' => 'ME',
        'MD' => 'MD',
        'MA' => 'MA',
        'MI' => 'MI',
        'MN' => 'MN',
        'MS' => 'MS',
        'MO' => 'MO',
        'MT' => 'MT',
        'NE' => 'NE',
        'NV' => 'NV',
        'NH' => 'NH',
        'NJ' => 'NJ',
        'NM' => 'NM',
        'NY' => 'NY',
        'NC' => 'NC',
        'ND' => 'ND',
        'OH' => 'OH',
        'OK' => 'OK',
        'OR' => 'OR',
        'PA' => 'PA',
        'RI' => 'RI',
        'SC' => 'SC',
        'SD' => 'SD',
        'TN' => 'TN',
        'TX' => 'TX',
        'UT' => 'UT',
        'VT' => 'VT',
        'VA' => 'VA',
        'WA' => 'WA',
        'WV' => 'WV',
        'WI' => 'WI',
        'WY' => 'WY',
    ];

    /**
     * @return array<string, string>
     */
    public function parse(string $address): array
    {
        $lines = $this->lines($address);
        $phone = $this->extractPhone($lines);
        $country_code = $this->extractCountry($lines);
        [$postal_code, $postal_country_code] = $this->extractPostalCode($lines);
        $country_code ??= $postal_country_code;
        [$city, $state, $region_country_code] = $this->extractLocality($lines, $country_code);
        $country_code ??= $region_country_code;
        $lines = array_values(array_filter($lines, fn(string $line): bool => $line !== ''));
        $address1 = array_shift($lines) ?? '';

        return array_filter([
            'address1' => $address1,
            'address2' => implode(', ', $lines),
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal_code,
            'country_code' => $country_code ?? '',
            'phone' => $phone,
        ], fn(string $value): bool => $value !== '');
    }

    /** @return array<int, string> */
    private function lines(string $address): array
    {
        $lines = preg_split('/\R+/u', trim($address)) ?: [];

        return array_values(array_filter(array_map(
            fn(string $line): string => $this->cleanLine($line),
            $lines,
        ), fn(string $line): bool => $line !== ''));
    }

    /** @param array<int, string> $lines */
    private function extractPhone(array &$lines): string
    {
        foreach ($lines as $index => $line) {
            if (! preg_match('/\b(?:tel(?:ephone)?|phone)\s*:?\s*(.+)$/i', $line, $matches)) {
                continue;
            }

            $phone = trim($matches[1]);
            $lines[$index] = $this->cleanLine(substr($line, 0, (int) strpos($line, $matches[0])));

            return $phone;
        }

        return '';
    }

    /** @param array<int, string> $lines */
    private function extractCountry(array &$lines): ?string
    {
        $countries = [
            'canada' => 'CA',
            'united states of america' => 'US',
            'united states' => 'US',
            'usa' => 'US',
            'us' => 'US',
        ];

        foreach ($lines as $index => $line) {
            foreach ($countries as $name => $code) {
                if (preg_match('/^' . preg_quote($name, '/') . '(?:[\s,]+(?<rest>.*))?$/i', $line, $matches)) {
                    $lines[$index] = $this->cleanLine($matches['rest'] ?? '');

                    return $code;
                }

                if (preg_match('/^(?<rest>.+?)[,\s]+' . preg_quote($name, '/') . '$/i', $line, $matches)) {
                    $lines[$index] = $this->cleanLine($matches['rest']);

                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $lines
     * @return array{0: string, 1: ?string}
     */
    private function extractPostalCode(array &$lines): array
    {
        foreach ($lines as $index => $line) {
            if (preg_match(self::CANADIAN_POSTAL_PATTERN, $line, $matches)) {
                $lines[$index] = $this->cleanLine(preg_replace(self::CANADIAN_POSTAL_PATTERN, '', $line, 1) ?? $line);

                return [strtoupper($matches[1] . ' ' . $matches[2]), 'CA'];
            }
        }

        foreach ($lines as $index => $line) {
            if (preg_match(self::US_POSTAL_PATTERN, $line, $matches)) {
                $lines[$index] = $this->cleanLine(preg_replace(self::US_POSTAL_PATTERN, '', $line, 1) ?? $line);

                return [$matches[0], 'US'];
            }
        }

        return ['', null];
    }

    /**
     * @param array<int, string> $lines
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function extractLocality(array &$lines, ?string $country_code): array
    {
        foreach ($this->regions($country_code) as $region => [$state, $region_country_code]) {
            for ($index = count($lines) - 1; $index >= 0; $index--) {
                $pattern = '/^(?<prefix>.+?)(?:,\s*|\s+)' . preg_quote($region, '/') . '\s*,?$/i';

                if (! preg_match($pattern, $lines[$index], $matches)) {
                    continue;
                }

                [$street, $city] = $this->splitRuralLocality($this->cleanLine($matches['prefix']));
                $lines[$index] = $street;

                return [$city, $state, $region_country_code];
            }
        }

        return ['', '', null];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function regions(?string $country_code): array
    {
        $regions = match ($country_code) {
            'CA' => array_map(fn(string $state): array => [$state, 'CA'], self::CANADIAN_PROVINCES),
            'US' => array_map(fn(string $state): array => [$state, 'US'], self::US_STATES),
            default => array_merge(
                array_map(fn(string $state): array => [$state, 'CA'], self::CANADIAN_PROVINCES),
                array_map(fn(string $state): array => [$state, 'US'], self::US_STATES),
            ),
        };

        uksort($regions, fn(string $left, string $right): int => strlen($right) <=> strlen($left));

        return $regions;
    }

    /** @return array{0: string, 1: string} */
    private function splitRuralLocality(string $prefix): array
    {
        if (preg_match('/^(?<street>(?:R\.?\s*R\.?|RURAL ROUTE)\s*\d+)\s+(?<city>.+)$/i', $prefix, $matches)) {
            return [$this->cleanLine($matches['street']), $this->cleanLine($matches['city'])];
        }

        return ['', $prefix];
    }

    private function cleanLine(string $line): string
    {
        $line = preg_replace('/\s+/u', ' ', trim($line)) ?? trim($line);

        return trim($line, " \t\n\r\0\x0B,");
    }
}
