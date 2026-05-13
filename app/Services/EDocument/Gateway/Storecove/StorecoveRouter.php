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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Services\EDocument\Gateway\Storecove\Routing\StorecoveRoutingRules;

class StorecoveRouter
{
    public function hasRoutingRules(string $countryCode): bool
    {
        return $this->routingRules->hasCountry($countryCode);
    }

    public function __construct(
        private ?StorecoveRoutingRules $routingRules = null,
    ) {
        $this->routingRules ??= new StorecoveRoutingRules();
    }

    /**
     * Resolve the routing identifier (rule column 3) for delivery.
     *
     * For most countries this is a scheme label like "SE:ORGNR".
     * For fixed-endpoint countries (e.g. SG Government) it may be a
     * composite "icd:endpointId" like "0195:SGUENT08GA0028A".
     *
     * @param  string  $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveRouting(string $country, ?string $classification = 'business'): string
    {
        return $this->routingRules->routingIdentifierFor($country, $classification);
    }

    /**
     * Resolve the tax scheme (rule column 2) for a country/classification.
     *
     * Returns empty string when no tax scheme applies (e.g. government
     * entities that route via a central gateway rather than a tax identifier).
     *
     * @param  string  $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveTaxScheme(string $country, ?string $classification = "business"): string
    {
        return $this->routingRules->taxIdentifierFor($country, $classification);
    }

    /**
     * Resolve the identifier scheme (rule column 1) for a country/classification.
     *
     * This is the primary identifier type (e.g. SG:UEN, SE:ORGNR) as opposed
     * to the tax-specific scheme in column 2. Used as a fallback when the tax
     * scheme is empty (e.g. SG Government).
     *
     * @param  string  $country
     * @param  ?string $classification
     * @return string
     */
    public function resolveIdentifierScheme(string $country, ?string $classification = "business"): string
    {
        return $this->routingRules->legalIdentifierFor($country, $classification);
    }

    /**
     * @return array{legal_identifier: string, tax_identifier: string}
     */
    public function identifiersFor(string $country, ?string $classification = 'business'): array
    {
        return $this->routingRules->identifiersFor($country, $classification);
    }

    /**
     * Checks whether a classification (business/government/individual) is routable
     * on the Peppol network for a given country.
     *
     * @param  string $country ISO 3166-2 country code
     * @param  string $classification business|government|individual
     * @return bool
     */
    public function isClassificationRoutable(string $country, string $classification): bool
    {
        return $this->routingRules->isClassificationRoutable($country, $classification);
    }

}
