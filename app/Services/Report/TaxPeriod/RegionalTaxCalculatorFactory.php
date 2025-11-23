<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report\TaxPeriod;

use App\Models\Company;

/**
 * Factory for creating region-specific tax calculators
 */
class RegionalTaxCalculatorFactory
{
    /**
     * Available calculator classes in priority order
     */
    private const CALCULATORS = [
        UsaTaxCalculator::class,
        // Add more region-specific calculators here in the future
        // CanadaTaxCalculator::class,
        // EuTaxCalculator::class,
        // AustraliaTaxCalculator::class,
        GenericTaxCalculator::class, // Always last as fallback
    ];

    /**
     * Create the appropriate calculator for a company
     */
    public static function create(Company $company): RegionalTaxCalculator
    {
        $country_iso = $company->country()->iso_3166_2;

        foreach (self::CALCULATORS as $calculator_class) {
            if ($calculator_class::supports($country_iso)) {
                return new $calculator_class();
            }
        }

        // Fallback to generic (should never reach here due to GenericTaxCalculator)
        return new GenericTaxCalculator();
    }

    /**
     * Check if a company has region-specific tax requirements
     */
    public static function hasRegionalCalculator(Company $company): bool
    {
        $calculator = self::create($company);
        return !($calculator instanceof GenericTaxCalculator);
    }
}
