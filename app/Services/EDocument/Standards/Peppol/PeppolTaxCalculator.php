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

use App\Models\Product;
use App\DataMapper\Tax\BaseRule;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\IdentificationCode;
use App\Services\EDocument\Standards\Peppol;

class PeppolTaxCalculator
{
    public function __construct(private Peppol $peppol)
    {
    }

    /**
     * getTaxType
     *
     * Calculates the PEPPOL code for the tax type
     *
     * @param  string $tax_id
     * @return string
     */
    public function getTaxType(string $tax_id = ''): string
    {
        $tax_type = null;

        switch ($tax_id) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
                $tax_type = 'S';
                break;
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                // $tax_type = 'AA';
                $tax_type = 'S'; //2026-01-14 - using AA breaks PEPPOL VALIDATION!!
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type =  'E';
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = 'Z';
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = 'AE';
                // no break
            case Product::PRODUCT_INTRA_COMMUNITY:
                $tax_type = 'K';
                break;
        }

        $company = $this->peppol->getCompany();
        $invoice = $this->peppol->getInvoiceModel();

        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "ES-CE", "ES-ML", "ES-CN", "SE", "IS", "LI", "NO", "CH"];

        if (empty($tax_type)) {
            if ((in_array($company->country()->iso_3166_2, $eu_states) && in_array($invoice->client->country->iso_3166_2, $eu_states)) && $invoice->company->country()->iso_3166_2 != $invoice->client->country->iso_3166_2) {
                $tax_type = 'K'; // EEA Exempt
            } elseif (!in_array($invoice->client->country->iso_3166_2, $eu_states)
                      || !in_array($company->country()->iso_3166_2, $eu_states)) {
                $tax_type = 'G'; //Free export item, VAT not charged
            } else {
                $tax_type = 'S'; //Standard rate
            }
        }

        if (in_array($invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML", "ES-CN"]) && $tax_type == 'S') {

            if ($invoice->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = 'L'; //Canary Islands general indirect tax
            } elseif (in_array($invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = 'M'; //Tax for production, services and importation in Ceuta and Melilla
            }

        }

        return $tax_type;
    }

    public function resolveTaxExemptReason($item, $ctc = null): mixed
    {
        $company = $this->peppol->getCompany();
        $invoice = $this->peppol->getInvoiceModel();

        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "ES-CE", "ES-ML", "ES-CN", "SE", "IS", "LI", "NO", "CH"];

        $company_country = $company->country()->iso_3166_2;
        $client_country = $invoice->client->country->iso_3166_2;
        $company_in_eu = in_array($company_country, $eu_states);
        $client_in_eu = in_array($client_country, $eu_states);

        // Non-EU company — use generic tax exempt categories
        if (!$company_in_eu) {
            if ($company_country != $client_country) {
                $tax_type = 'O';
                $reason_code = 'vatex-eu-o';
                $reason = 'Not subject to VAT';
            } else {
                $tax_type = 'E';
                $reason_code = 'vatex-eu-o';
                $reason = 'Not subject to VAT';
            }
        } elseif ($item->tax_id == '9') {
            $tax_type = 'AE'; // EEA Exempt
            $reason_code = 'vatex-eu-ae';
            $reason = 'Reverse charge';
        } elseif ($company_in_eu && $client_in_eu && $company_country != $client_country) {
            $tax_type = 'K'; // EEA Exempt
            $reason_code = 'vatex-eu-ic';
            $reason = 'Intra-Community supply';
        } elseif (!$client_in_eu) {
            $tax_type = 'G'; //Free export item, VAT not charged
            $reason_code = 'vatex-eu-g';
            $reason = 'Export outside the EU';
        } elseif ($company_country == $client_country) {
            $tax_type = 'E';
            $reason_code = "vatex-eu-o";
            $reason = 'Services outside scope of tax';
        } else {
            $tax_type = 'O';
            $reason_code = "vatex-eu-o";
            $reason = 'Services outside scope of tax';
        }

        $this->peppol->setTaxCategoryId($tax_type);

        //no vat, build a single tax category for tax exemption

        $taxCategory = new \InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory();
        $taxCategory->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $taxCategory->ID->value = $tax_type;

        if ($this->peppol->getTaxCategoryId() != 'O') {
            $taxCategory->Percent = '0';
        }

        $taxScheme = new \InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme();
        $taxScheme->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $taxScheme->ID->value = $this->standardizeTaxSchemeId('vat');
        $taxCategory->TaxScheme = $taxScheme;

        $terc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\TaxExemptionReasonCode();
        $terc->value = $reason_code;
        $taxCategory->TaxExemptionReasonCode = $terc;
        $taxCategory->TaxExemptionReason = $reason;
    
        $this->peppol->setGlobalTaxCategories([$taxCategory]);

        if ($ctc) {
            $ctc->ID->value = $tax_type;

            if ($this->peppol->getTaxCategoryId() != 'O') {
                $ctc->Percent = '0';
            }

            return $ctc;
        }

        return $tax_type;

    }

    /**
     * getAllUsedTaxes
     *
     * Build a full tax category property based on all
     * of the item taxes that have been applied to the invoice.
     *
     * @return self
     */
    public function getAllUsedTaxes(): self
    {
        $this->peppol->setGlobalTaxCategories([]);
        $invoice = $this->peppol->getInvoiceModel();

        $categories = [];

        collect($invoice->line_items)
            ->flatMap(function ($item) {
                return collect([1, 2, 3])
                    ->map(fn($i) => [
                        'name' => $item->{"tax_name{$i}"} ?? '',
                        'percentage' => $item->{"tax_rate{$i}"} ?? 0,
                        'scheme' => $this->getTaxType($item->tax_id),
                    ])
                    ->filter(fn($tax) => strlen($tax['name']) > 1);
            })
            ->unique(fn($tax) => $tax['percentage'] . '_' . $tax['name'])
            ->values()
            ->each(function ($tax) use (&$categories) {

                $taxCategory = new \InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory();
                $taxCategory->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
                $taxCategory->ID->value = $tax['scheme'];
                $taxCategory->Percent = (string) $tax['percentage'];
                $taxScheme = new \InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme();
                $taxScheme->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
                $taxScheme->ID->value = $this->standardizeTaxSchemeId($tax['name']);
                $taxCategory->TaxScheme = $taxScheme;

                $categories[] = $taxCategory;

            });

        $this->peppol->setGlobalTaxCategories($categories);

        return $this;

    }

    /**
     * setTaxBreakdown
     *
     * @return Peppol
     */
    public function setTaxBreakdown(): Peppol
    {
        $invoice = $this->peppol->getInvoiceModel();
        $calc = $this->peppol->getCalc();
        $p_invoice = $this->peppol->getPeppolDocument();

        $tax_total = new TaxTotal();
        $taxes = $calc->getTaxMap();

        if (count($taxes) < 1 || (count($taxes) == 1 && $invoice->total_taxes == 0)) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $invoice->client->currency()->code;
            $tax_amount->amount = (string) 0;
            $tax_total->TaxAmount = $tax_amount;

            $tax_subtotal = new TaxSubtotal();

            // Required: TaxableAmount (BT-116)
            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $invoice->client->currency()->code;
            $taxable_amount->amount = (string) round($this->peppol->normalizeAmount($invoice->amount), 2);

            $tax_subtotal->TaxableAmount = $taxable_amount;

            $subtotal_tax_amount = new TaxAmount();
            $subtotal_tax_amount->currencyID = $invoice->client->currency()->code;

            $subtotal_tax_amount->amount = (string) 0;

            $tax_subtotal->TaxAmount = $subtotal_tax_amount;

            // Required: TaxCategory (BG-23)
            $tax_category = new TaxCategory();

            // Required: TaxCategory ID (BT-118)
            $category_id = new ID();
            $category_id->value = $this->peppol->getTaxCategoryId(); // Exempt

            $tax_category->ID = $category_id;

            // Required: TaxScheme (BG-23)
            $tax_scheme = new TaxScheme();
            $scheme_id = new ID();
            $scheme_id->value = $this->standardizeTaxSchemeId("taxname");
            $tax_scheme->ID = $scheme_id;
            $tax_category->TaxScheme = $tax_scheme;

            $tax_subtotal->TaxCategory = $this->peppol->getGlobalTaxCategories()[0];

            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $p_invoice->TaxTotal[] = $tax_total;

            $this->peppol->setPeppolDocument($p_invoice);

            return $this->peppol;

        }

        foreach ($taxes as $key => $grouped_tax) {
            // Required: TaxAmount (BT-110)
            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $invoice->client->currency()->code;
            // $tax_amount->amount = (string) round($this->peppol->normalizeAmount($invoice->total_taxes), 2);
                $tax_amount->amount = (string) \App\Utils\BcMath::round((string) $this->peppol->normalizeAmount($invoice->total_taxes), 2);
                $tax_total->TaxAmount = $tax_amount;

            // Required: TaxSubtotal (BG-23)
            $tax_subtotal = new TaxSubtotal();

            // Required: TaxableAmount (BT-116)
            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $invoice->client->currency()->code;

            if (floatval($grouped_tax['total']) === 0.0) {
                $taxable_amount->amount = (string) round($this->peppol->normalizeAmount($invoice->amount), 2);
            } else {
                $taxable_amount->amount = (string) round($this->peppol->normalizeAmount($grouped_tax['base_amount']), 2);
            }
            $tax_subtotal->TaxableAmount = $taxable_amount;

            // Required: TaxAmount (BT-117)
            $subtotal_tax_amount = new TaxAmount();
            $subtotal_tax_amount->currencyID = $invoice->client->currency()->code;

            // $subtotal_tax_amount->amount = (string) round($this->peppol->normalizeAmount($grouped_tax['total']), 2);
            $subtotal_tax_amount->amount = (string) \App\Utils\BcMath::round((string) $this->peppol->normalizeAmount($grouped_tax['total']), 2);

            $tax_subtotal->TaxAmount = $subtotal_tax_amount;

            // Required: TaxCategory (BG-23)
            $tax_category = new TaxCategory();

            // Required: TaxCategory ID (BT-118)
            $category_id = new ID();
            $category_id->value = $this->getTaxType($grouped_tax['tax_id']); // Standard rate

            $tax_category->ID = $category_id;

            // Required: TaxCategory Rate (BT-119)
            if ($grouped_tax['tax_rate'] > 0) {
                $tax_category->Percent = (string) $grouped_tax['tax_rate'];
            }

            // Required: TaxScheme (BG-23)
            $tax_scheme = new TaxScheme();
            $scheme_id = new ID();
            $scheme_id->value = $this->standardizeTaxSchemeId("taxname");
            $tax_scheme->ID = $scheme_id;
            $tax_category->TaxScheme = $tax_scheme;

            $tax_subtotal->TaxCategory = $tax_category;

            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $p_invoice->TaxTotal[] = $tax_total;
        }

        $this->peppol->setPeppolDocument($p_invoice);

        return $this->peppol;
    }

    /**
     * calculateTaxMap
     *
     * Generates a standard tax_map entry for a given $amount
     *
     * Iterates through all of the globalTaxCategories found in the document
     *
     * @param  float $amount
     * @return self
     */
    public function calculateTaxMap($amount): self
    {
        foreach ($this->peppol->getGlobalTaxCategories() as $tc) {

            $this->peppol->addToTaxMap([
                'taxableAmount' => $amount,
                'taxAmount' => $amount * ($tc->Percent / 100),
                'percentage' => $tc->Percent,
            ]);

        }

        return $this;
    }

    public function getJurisdiction()
    {
        $company = $this->peppol->getCompany();
        $invoice = $this->peppol->getInvoiceModel();

        //calculate nexus
        $country_code = $company->country()->iso_3166_2;
        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_countries = $br->eu_country_codes;

        if ($invoice->client->country->iso_3166_2 == $company->country()->iso_3166_2) {
            //Domestic Sales
            $country_code = $company->country()->iso_3166_2;
        } elseif (in_array($country_code, $eu_countries) && !in_array($invoice->client->country->iso_3166_2, $eu_countries)) {
            //EU => FOREIGN sale
        } elseif (in_array($invoice->client->country->iso_3166_2, $eu_countries)) {
            // EU Sale
            if ((isset($company->tax_data->regions->EU->has_sales_above_threshold) && $company->tax_data->regions->EU->has_sales_above_threshold) || !$invoice->client->has_valid_vat_number) { //over threshold - tax in buyer country

                $country_code = $invoice->client->country->iso_3166_2;

                if (isset($company->tax_data->regions->EU->subregions->{$country_code}->vat_number)) {
                    $this->peppol->setOverrideVatNumber($company->tax_data->regions->EU->subregions->{$country_code}->vat_number);
                }
            }
        }

        $jurisdiction = new \InvoiceNinja\EInvoice\Models\Peppol\AddressType\JurisdictionRegionAddress();
        $country = new Country();
        $ic = new IdentificationCode();
        $ic->value = $country_code;
        $country->IdentificationCode = $ic;
        $jurisdiction->Country = $country;
        $addressTypeCode = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\AddressTypeCode();
        $addressTypeCode->value = 'JURISDICTION';  // or the appropriate code from PEPPOL spec
        $jurisdiction->AddressTypeCode = $addressTypeCode;

        return $jurisdiction;

    }

    public function standardizeTaxSchemeId(string $tax_name): string
    {

        $br = new BaseRule();
        $eu_countries = $br->eu_country_codes;

        // If company is in EU, standardize to VAT
        // if (in_array($this->peppol->getCompany()->country()->iso_3166_2, $eu_countries)) {
        return "VAT";
        // }

        // For non-EU countries, return original or handle specifically
        // return $this->standardizeTaxSchemeId($tax_name);
    }

    /**
     * getTaxable
     *
     * @return float
     */
    public function getTaxable(): float
    {
        $invoice = $this->peppol->getInvoiceModel();
        $total = 0;

        foreach ($invoice->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($invoice->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        $total = round($total, 2);

        if ($invoice->discount > 0) {
            if ($invoice->is_amount_discount) {
                $total -= $invoice->discount;
            } else {
                $total *= (100 - $invoice->discount) / 100;

            }
        }

        //** Surcharges are taxable regardless, if control is needed over taxable components, add it as a line item! */
        if ($invoice->custom_surcharge1 > 0) {
            $total += $invoice->custom_surcharge1;
        }

        if ($invoice->custom_surcharge2 > 0) {
            $total += $invoice->custom_surcharge2;
        }

        if ($invoice->custom_surcharge3 > 0) {
            $total += $invoice->custom_surcharge3;
        }

        if ($invoice->custom_surcharge4 > 0) {
            $total += $invoice->custom_surcharge4;
        }

        return round($this->peppol->normalizeAmount($total), 2);
    }
}
