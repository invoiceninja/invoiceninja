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

use App\DataMapper\Tax\BaseRule;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice;
use App\Services\EDocument\Gateway\Storecove\Models\Credit;

/**
 * Determines the tax nexus country for a Storecove document submission.
 *
 * Tax nexus rules:
 *  - Domestic (same country): company country
 *  - EU company → non-EU client: company country
 *  - Non-EU company → EU client: client country
 *  - EU cross-border B2C under threshold: company country
 *  - EU cross-border B2C over threshold: client country (destination VAT)
 *  - EU cross-border B2B with valid VAT: company country
 *  - Non-EU/non-EU: company country (fallback)
 *  - Company has tax registration in client region: client country
 *  - DE → DE government: removes supplier VAT identifiers
 */
class NexusResolver
{
    private string $nexus;

    private array $errors = [];

    public function __construct(
        private \App\Models\Invoice|\App\Models\Credit $invoice,
        private Invoice|Credit $storecoveInvoice,
        private StorecoveRouter $router,
    ) {}

    public function getNexus(): string
    {
        return $this->nexus;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Determines the tax nexus country based on company/client locations,
     * EU thresholds, B2B/B2C classification, and VAT registration status.
     *
     * @return self
     */
    public function resolve(): self
    {

        $company_country_code = $this->invoice->company->country()->iso_3166_2;
        $client_country_code = $this->invoice->client->country->iso_3166_2;
        $eu_countries = BaseRule::EU_COUNTRY_CODES;

        if ($client_country_code == $company_country_code) {
            nlog("domestic sales");
            $this->nexus = $company_country_code;
        } elseif (in_array($company_country_code, $eu_countries) && !in_array($client_country_code, $eu_countries)) {
            nlog("non eu");
            $this->nexus = $company_country_code;
        } elseif (!in_array($company_country_code, $eu_countries) && in_array($client_country_code, $eu_countries)) {
            nlog("non-eu to eu");
            $this->nexus = $client_country_code;
        } elseif (in_array($client_country_code, $eu_countries)) {
            $this->resolveEuCrossBorder($company_country_code, $client_country_code, $eu_countries);
        }

        if (!isset($this->nexus)) {
            $this->resolveFallback($company_country_code, $client_country_code);
        }

        if ($company_country_code == 'DE' && $client_country_code == 'DE' && $this->invoice->client->classification == 'government') {
            $this->removeSupplierVatNumber();
        }

        return $this;
    }

    /**
     * Resolves nexus for EU cross-border scenarios considering B2B/B2C
     * classification, threshold status, and VAT registration.
     */
    private function resolveEuCrossBorder(string $company_country_code, string $client_country_code, array $eu_countries): void
    {
        $is_over_threshold = isset($this->invoice->company->tax_data->regions->EU->has_sales_above_threshold)
                           && $this->invoice->company->tax_data->regions->EU->has_sales_above_threshold;

        $is_b2c = strlen($this->invoice->client->vat_number ?? '') < 2
                || !($this->invoice->client->has_valid_vat_number ?? false)
                || $this->invoice->client->classification == 'individual';

        if ($is_b2c && !$is_over_threshold && strlen($this->invoice->company->settings->vat_number ?? '') < 2) {
            nlog("no company vat");
            $this->nexus = $company_country_code;
        } elseif ($is_b2c) {
            if ($is_over_threshold) {
                if (strlen($this->invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number ?? '') < 2) {
                    $this->nexus = $client_country_code;
                    $this->errors[] = "Tax Nexus is client country ({$client_country_code}) - however VAT number not present for this region. Document not sent!";
                    return;
                }
                nlog("B2C");
                $this->nexus = $client_country_code;
                $this->setupDestinationVAT($client_country_code);
            } else {
                nlog("under threshold origin country");
                $this->nexus = $company_country_code;
            }
        } else {
            nlog("B2B with valid vat");
            $this->nexus = $company_country_code;
        }
    }

    /**
     * Fallback nexus resolution when no EU rules applied:
     * checks if the company has a tax registration in the client's region.
     */
    private function resolveFallback(string $company_country_code, string $client_country_code): void
    {
        $br = new BaseRule();
        $client_region = $br->region_codes[$client_country_code] ?? null;

        if ($client_region && $this->companyHasTaxRegistration($client_region, $client_country_code)) {
            nlog("fallback nexus to client country - company has tax registration in {$client_country_code}");
            $this->nexus = $client_country_code;
        } else {
            nlog("fallback nexus to company country - export/no registration");
            $this->nexus = $company_country_code;
        }
    }

    /**
     * Checks whether the company has a VAT registration in the given region and country.
     */
    private function companyHasTaxRegistration(string $region, string $country_code): bool
    {
        $vat_number = $this->invoice->company->tax_data->regions->{$region}->subregions->{$country_code}->vat_number ?? '';

        return strlen($vat_number) > 1;
    }

    /**
     * Removes VAT public identifiers from the supplier party,
     * required for DE government invoices (XRechnung).
     */
    private function removeSupplierVatNumber(): void
    {
        $asp = $this->storecoveInvoice->getAccountingSupplierParty();
        $asp->setPublicIdentifiers([]);
        $this->storecoveInvoice->setAccountingSupplierParty($asp);
    }

    /**
     * Configures destination-country VAT for B2C cross-border EU sales
     * by enabling consumer tax mode and adding the supplier's destination VAT identifier.
     */
    private function setupDestinationVAT(string $client_country_code): void
    {
        $this->storecoveInvoice->setConsumerTaxMode(true);
        $id = $this->invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number;
        $scheme = $this->router->resolveTaxScheme($client_country_code, $this->invoice->client->classification ?? 'individual');

        $pi = new \App\Services\EDocument\Gateway\Storecove\Models\PublicIdentifiers($scheme, $id);
        $asp = $this->storecoveInvoice->getAccountingSupplierParty();
        $asp->addPublicIdentifiers($pi);
        $this->storecoveInvoice->setAccountingSupplierParty($asp);
    }
}
