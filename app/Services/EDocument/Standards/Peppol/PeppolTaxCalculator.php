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

use App\DataMapper\Tax\BaseRule;
use App\Models\Product;
use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Standards\Peppol;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\JurisdictionRegionAddress;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\IdentificationCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\TaxExemptionReasonCode;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;

class PeppolTaxCalculator
{
    private ?JurisdictionRegionAddress $jurisdiction = null;

    public function __construct(private Peppol $peppol) {}

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
                break;
            case Product::PRODUCT_INTRA_COMMUNITY:
                $tax_type = 'K';
                break;
        }

        $company = $this->peppol->getCompany();
        $invoice = $this->peppol->getInvoiceModel();

        $eu_states = BaseRule::EU_TAX_EXEMPT_COUNTRY_CODES;

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

    /**
     * VAT exemption metadata for Peppol / EN 16931 (BT-120, BT-121) on zero-rated lines.
     *
     * @param  object{tax_id?: string|int}  $item
     * @return array{tax_type: string, reason_code: ?string, reason: ?string}
     */
    public function getExemptionReasonMetadataForItem(object $item): array
    {
        $company = $this->peppol->getCompany();
        $invoice = $this->peppol->getInvoiceModel();

        $eu_states = BaseRule::EU_TAX_EXEMPT_COUNTRY_CODES;

        $company_country = $company->country()->iso_3166_2;
        $client_country = $invoice->client->country->iso_3166_2;
        $company_in_eu = in_array($company_country, $eu_states);
        $client_in_eu = in_array($client_country, $eu_states);

        $tax_id = (string) ($item->tax_id ?? '');

        // Preserve outside-scope treatment for non-EU cross-border sellers.
        if (!$company_in_eu && $company_country !== $client_country) {
            return ['tax_type' => 'O', 'reason_code' => 'vatex-eu-o', 'reason' => 'Not subject to VAT'];
        }

        if ($company_country === $client_country && $tax_id === (string) Product::PRODUCT_TYPE_ZERO_RATED) {
            return ['tax_type' => 'Z', 'reason_code' => null, 'reason' => null];
        }

        if ($tax_id === (string) Product::PRODUCT_TYPE_REVERSE_TAX) {
            return ['tax_type' => 'AE', 'reason_code' => 'vatex-eu-ae', 'reason' => 'Reverse charge'];
        }

        if ($company_in_eu && $client_in_eu && $company_country != $client_country) {
            return ['tax_type' => 'K', 'reason_code' => 'vatex-eu-ic', 'reason' => 'Intra-Community supply'];
        }

        if ($company_country === $client_country) {
            if ($tax_id === (string) Product::PRODUCT_TYPE_EXEMPT || $invoice->client->is_tax_exempt) {
                return ['tax_type' => 'E', 'reason_code' => null, 'reason' => 'Exempt from VAT'];
            }

            throw new PeppolValidationException(
                'Select a tax category for zero-tax line "' . ($item->product_key ?? '') . '": zero rated, exempt, or reverse charge.',
                'line_items.tax_id',
            );
        }

        if (!$client_in_eu) {
            return ['tax_type' => 'G', 'reason_code' => 'vatex-eu-g', 'reason' => 'Export outside the EU'];
        }

        return ['tax_type' => 'O', 'reason_code' => 'vatex-eu-o', 'reason' => 'Not subject to VAT'];
    }

    /** Resolve and register the category once, for both lines and VAT breakdowns. */
    public function resolveLineTaxCategory(object $item, float $rate, string $name): TaxCategory
    {
        $meta = $rate == 0.0
            ? $this->getExemptionReasonMetadataForItem($item)
            : ['tax_type' => $this->getTaxType((string) $item->tax_id), 'reason_code' => null, 'reason' => null];

        $category = new TaxCategory();
        $category->ID = new ID();
        $category->ID->value = $meta['tax_type'];
        if ($meta['tax_type'] !== 'O') {
            $category->Percent = rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.');
        }
        $category->TaxScheme = new TaxScheme();
        $category->TaxScheme->ID = new ID();
        $category->TaxScheme->ID->value = $this->standardizeTaxSchemeId($name);
        if ($meta['reason_code'] !== null) {
            $category->TaxExemptionReasonCode = new TaxExemptionReasonCode();
            $category->TaxExemptionReasonCode->value = $meta['reason_code'];
        }
        if ($meta['reason'] !== null) {
            $category->TaxExemptionReason = $meta['reason'];
        }

        $categories = $this->peppol->getGlobalTaxCategories() ?? [];
        $key = self::categoryKey($category);
        $categories[$key] = $category;
        if (count($categories) > 1 && array_filter($categories, fn ($c) => $c->ID->value === 'O')) {
            throw new PeppolValidationException('Outside-scope VAT cannot be combined with other VAT categories.', 'line_items.tax_id');
        }
        $this->peppol->setGlobalTaxCategories($categories);
        $this->peppol->setTaxCategoryId($meta['tax_type']);

        return $category;
    }

    public static function categoryKey(object $category): string
    {
        return $category->ID->value . ':' . (isset($category->Percent) ? number_format((float) $category->Percent, 6, '.', '') : '');
    }

    /**
     * Build BG-23 from the same emitted lines and document adjustments as BG-25.
     * Category is part of the key: E/0 and Z/0 must never share a taxable base.
     */
    public function setTaxBreakdown(): Peppol
    {
        $invoice = $this->peppol->getInvoiceModel();
        $document = $this->peppol->getPeppolDocument();
        $currency = $invoice->client->currency()->code;
        $groups = $this->peppol->lineNetByTaxCategory();
        $categories = $this->peppol->getGlobalTaxCategories() ?? [];

        foreach ($document->AllowanceCharge ?? [] as $adjustment) {
            foreach ($adjustment->TaxCategory as $category) {
                $key = self::categoryKey($category);
                $amount = (string) $adjustment->Amount->amount;
                $groups[$key] = ($adjustment->ChargeIndicator === 'true')
                    ? bcadd((string) ($groups[$key] ?? 0), $amount, 6)
                    : bcsub((string) ($groups[$key] ?? 0), $amount, 6);
            }
        }

        $total = new TaxTotal();
        $total->TaxAmount = new TaxAmount();
        $total->TaxAmount->currencyID = $currency;
        $total->TaxAmount->amount = '0';
        $total->TaxSubtotal = [];

        foreach ($groups as $key => $base) {
            if (!isset($categories[$key])) {
                throw new PeppolValidationException('Missing VAT category for breakdown ' . $key, 'line_items.tax_id');
            }
            $category = $categories[$key];
            $base = (string) \App\Utils\BcMath::round((string) $base, 2);
            $amount = (string) \App\Utils\BcMath::round(
                bcmul($base, bcdiv((string) ($category->Percent ?? 0), '100', 8), 6), 2,
            );
            $subtotal = new TaxSubtotal();
            $subtotal->TaxableAmount = new TaxableAmount();
            $subtotal->TaxableAmount->currencyID = $currency;
            $subtotal->TaxableAmount->amount = $base;
            $subtotal->TaxAmount = new TaxAmount();
            $subtotal->TaxAmount->currencyID = $currency;
            $subtotal->TaxAmount->amount = $amount;
            $subtotal->TaxCategory = clone $category;
            $total->TaxSubtotal[] = $subtotal;
            $total->TaxAmount->amount = bcadd((string) $total->TaxAmount->amount, $amount, 2);
        }

        $document->TaxTotal = [$total];
        if (isset($document->LegalMonetaryTotal)) {
            $lmt = $document->LegalMonetaryTotal;
            $inclusive = bcadd((string) $lmt->TaxExclusiveAmount->amount, (string) $total->TaxAmount->amount, 2);
            $lmt->TaxInclusiveAmount->amount = $inclusive;
            $lmt->PayableAmount->amount = $inclusive;
        }
        $this->peppol->setPeppolDocument($document);

        return $this->peppol;
    }

    public function getJurisdiction(): JurisdictionRegionAddress
    {
        return $this->jurisdiction;
    }

    public function setJurisdiction(): self
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
                    $this->peppol->setOverrideVatNumber($company->tax_data->regions->EU->subregions->{$country_code}->vat_number, $country_code);
                }
            }
        }

        $jurisdiction = new JurisdictionRegionAddress();
        $country = new Country();
        $ic = new IdentificationCode();
        $ic->value = $country_code;
        $country->IdentificationCode = $ic;
        $jurisdiction->Country = $country;
        $addressTypeCode = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\AddressTypeCode();
        $addressTypeCode->value = 'JURISDICTION';  // or the appropriate code from PEPPOL spec
        $jurisdiction->AddressTypeCode = $addressTypeCode;

        $this->jurisdiction = $jurisdiction;

        return $this;

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
