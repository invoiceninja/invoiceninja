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

namespace App\Services\EDocument\Standards\Validation\Peppol;

use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\App;
use App\Services\EDocument\Standards\Peppol;
use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Standards\Validation\EntityLevelInterface;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;

class EntityLevel implements EntityLevelInterface
{
    private array $eu_country_codes = [
        'AT', // Austria
        'BE', // Belgium
        'BG', // Bulgaria
        'CY', // Cyprus
        'CZ', // Czech Republic
        'DE', // Germany
        'DK', // Denmark
        'EE', // Estonia
        'ES', // Spain
        'ES-CN', // Canary Islands
        'ES-CE', // Ceuta
        'ES-ML', // Melilla
        'FI', // Finland
        'FR', // France
        'GR', // Greece
        'HR', // Croatia
        'HU', // Hungary
        'IE', // Ireland
        'IT', // Italy
        'LT', // Lithuania
        'LU', // Luxembourg
        'LV', // Latvia
        'MT', // Malta
        'NL', // Netherlands
        'PL', // Poland
        'PT', // Portugal
        'RO', // Romania
        'SE', // Sweden
        'SI', // Slovenia
        'SK', // Slovakia
    ];

    private array $client_fields = [
        'address1',
        'city',
        'state',
        'postal_code',
        'country_id',
    ];

    private array $company_settings_fields = [
        'address1',
        'city',
        // 'state',
        'postal_code',
        'country_id',
    ];

    /**
     * VAT number validation regex patterns for EU countries.
     * Patterns validate format only - they do not verify checksums or actual validity.
     * Patterns allow optional country prefix (e.g., "AT" or "ATU12345678").
     */
    private array $vat_number_regex = [
        'AT' => '/^(AT)?U\d{8}$/i',
        'BE' => '/^(BE)?[01]\d{9}$/i',
        'BG' => '/^(BG)?\d{9,10}$/i',
        'CY' => '/^(CY)?\d{8}[A-Z]$/i',
        'CZ' => '/^(CZ)?\d{8,10}$/i',
        'DE' => '/^(DE)?\d{9}$/i',
        'DK' => '/^(DK)?\d{8}$/i',
        'EE' => '/^(EE)?\d{9}$/i',
        'ES' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i',
        'ES-CN' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i',
        'ES-CE' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i',
        'ES-ML' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i',
        'FI' => '/^(FI)?\d{8}$/i',
        'FR' => '/^(FR)?[A-HJ-NP-Z0-9]{2}\d{9}$/i',
        'GR' => '/^(GR|EL)?\d{9}$/i',
        'HR' => '/^(HR)?\d{11}$/i',
        'HU' => '/^(HU)?\d{8}$/i',
        'IE' => '/^(IE)?\d[A-Z0-9\+\*]\d{5}[A-Z]{1,2}$/i',
        'IT' => '/^(IT)?\d{11}$/i',
        'LT' => '/^(LT)?(\d{9}|\d{12})$/i',
        'LU' => '/^(LU)?\d{8}$/i',
        'LV' => '/^(LV)?\d{11}$/i',
        'MT' => '/^(MT)?\d{8}$/i',
        'NL' => '/^(NL)?\d{9}B\d{2}$/i',
        'PL' => '/^(PL)?\d{10}$/i',
        'PT' => '/^(PT)?\d{9}$/i',
        'RO' => '/^(RO)?\d{2,10}$/i',
        'SE' => '/^(SE)?\d{12}$/i',
        'SI' => '/^(SI)?\d{8}$/i',
        'SK' => '/^(SK)?\d{10}$/i',
    ];

    private array $vat_number_formats = [
        'AT' => 'ATU + 8 digits (e.g. ATU12345678)',
        'BE' => 'BE + 0/1 + 9 digits (e.g. BE0123456789)',
        'BG' => 'BG + 9-10 digits (e.g. BG123456789)',
        'CY' => 'CY + 8 digits + 1 letter (e.g. CY12345678A)',
        'CZ' => 'CZ + 8-10 digits (e.g. CZ12345678)',
        'DE' => 'DE + 9 digits (e.g. DE123456789)',
        'DK' => 'DK + 8 digits (e.g. DK12345678)',
        'EE' => 'EE + 9 digits (e.g. EE123456789)',
        'ES' => 'ES + letter/digit + 7 digits + letter/digit (e.g. ESA1234567B)',
        'ES-CN' => 'ES + letter/digit + 7 digits + letter/digit (e.g. ESA1234567B)',
        'ES-CE' => 'ES + letter/digit + 7 digits + letter/digit (e.g. ESA1234567B)',
        'ES-ML' => 'ES + letter/digit + 7 digits + letter/digit (e.g. ESA1234567B)',
        'FI' => 'FI + 8 digits (e.g. FI12345678)',
        'FR' => 'FR + 2 alphanumeric + 9 digits (e.g. FRXX123456789)',
        'GR' => 'EL + 9 digits (e.g. EL123456789)',
        'HR' => 'HR + 11 digits (e.g. HR12345678901)',
        'HU' => 'HU + 8 digits (e.g. HU12345678)',
        'IE' => 'IE + digit + alphanumeric + 5 digits + 1-2 letters (e.g. IE1A23456B)',
        'IT' => 'IT + 11 digits (e.g. IT12345678901)',
        'LT' => 'LT + 9 or 12 digits (e.g. LT123456789)',
        'LU' => 'LU + 8 digits (e.g. LU12345678)',
        'LV' => 'LV + 11 digits (e.g. LV12345678901)',
        'MT' => 'MT + 8 digits (e.g. MT12345678)',
        'NL' => 'NL + 9 digits + B + 2 digits (e.g. NL123456789B01)',
        'PL' => 'PL + 10 digits (e.g. PL1234567890)',
        'PT' => 'PT + 9 digits (e.g. PT123456789)',
        'RO' => 'RO + 2-10 digits (e.g. RO1234567890)',
        'SE' => 'SE + 12 digits (e.g. SE123456789012)',
        'SI' => 'SI + 8 digits (e.g. SI12345678)',
        'SK' => 'SK + 10 digits (e.g. SK1234567890)',
    ];

    private array $company_fields = [
        // 'legal_entity_id',
        // 'vat_number IF NOT an individual
    ];

    private array $invoice_fields = [
        // 'number',
    ];

    private array $errors = [];

    public function __construct() {}

    private function init(string $locale): self
    {

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($locale);

        return $this;

    }

    public function checkClient(Client $client): array
    {
        $this->init($client->locale());

        $this->errors['client'] = $this->testClientState($client);
        $this->errors['passes'] = count($this->errors['client']) == 0;

        return $this->errors;

    }

    public function checkCompany(Company $company): array
    {

        $this->init($company->locale());
        $this->errors['company'] = $this->testCompanyState($company);
        $this->errors['passes'] = count($this->errors['company']) == 0;

        return $this->errors;

    }

    public function checkRecurringInvoice(RecurringInvoice $recurring_invoice): array
    {
        return ['passes' => true];
    }

    public function checkInvoice(Invoice|Credit $invoice): array
    {
        $this->init($invoice->client->locale());

        $this->errors['invoice'] = [];
        $this->errors['client'] = $this->testClientState($invoice->client);        
        $this->errors['company'] = $this->testCompanyState($invoice->client); // uses client level settings which is what we want

        if (count($this->errors['client']) > 0) {

            $this->errors['passes'] = false;
            return $this->errors;

        }

        $p = new Peppol($invoice);

        $xml = false;

        try {
            $xml = $p->run()->toXml();

            if (count($p->getErrors()) >= 1) {

                foreach ($p->getErrors() as $error) {
                    $this->errors['invoice'][] = $error;
                }
            }

        } catch (PeppolValidationException $e) {
            $this->errors['invoice'] = ['field' => $e->getInvalidField(), 'label' => $e->getInvalidField()];
        } catch (\Throwable $th) {

        }

        if ($xml) {
            // Second pass through the XSLT validator
            $xslt = new XsltDocumentValidator($xml);
            $errors = $xslt->validate()->getErrors();

            if (isset($errors['stylesheet']) && count($errors['stylesheet']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['stylesheet']);
            }

            if (isset($errors['general']) && count($errors['general']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['general']);
            }

            if (isset($errors['xsd']) && count($errors['xsd']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['xsd']);
            }

        }

        $this->checkNexus($invoice->client);

        $this->errors['passes'] = count($this->errors['invoice']) == 0 && count($this->errors['client']) == 0 && count($this->errors['company']) == 0;

        return $this->errors;

    }

    private function testClientState(Client $client): array
    {

        $errors = [];

        foreach ($this->client_fields as $field) {

            if ($field == 'country_id' && $client->country_id >= 1) {
                continue;
            }

            if (in_array($field, ['address1', 'address2', 'city', 'state', 'postal_code']) && strlen($client->address1 ?? '') < 2) {
                $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];
            }

            if ($this->validString($client->{$field})) {
                continue;
            }

        }

        // Validate required client identifiers based on country routing rules
        if (!$client->country) {
            $errors[] = ['field' => 'country_id', 'label' => ctrans("texts.country")];
            return $errors;
        }

        // Only validate identifier requirements for countries supported by Peppol or in the EU
        $br = new \App\DataMapper\Tax\BaseRule();
        $supported_countries = array_unique(array_merge(
            $br->peppol_business_countries,
            $br->peppol_government_countries,
            $this->eu_country_codes,
        ));

        if (in_array($client->country->iso_3166_2, $supported_countries)) {
            $router = new StorecoveRouter();
            $required = $router->resolveRequiredClientFields(
                $client->country->iso_3166_2,
                $client->classification ?? 'business'
            );

            foreach ($required as $field => $scheme) {
                if (!$this->validString($client->{$field})) {
                    $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}") . " ({$scheme})"];
                } elseif (!$router->validateIdentifierFormat($scheme, $client->{$field})) {
                    $errors[] = ['field' => $field, 'label' => ctrans("texts.invalid_{$field}_format") . " ({$scheme})"];
                }
            }
        }

        //Primary contact email is present.
        if ($client->present()->email() == 'No Email Set') {
            $errors[] = ['field' => 'email', 'label' => ctrans("texts.email")];
        }


        if ($client->country_id && $client->country) {
            $non_routable = $client->checkDeliveryNetwork();

            if (is_string($non_routable)) {
                $errors[] = ['field' => 'classification', 'label' => $non_routable];
            }
        }



        return $errors;

    }

    private function testCompanyState(mixed $entity): array
    {

        $client = false;
        $vendor = false;
        $settings_object = false;
        $company = false;

        if ($entity instanceof Client) {
            $client = $entity;
            $company = $entity->company;
            $settings_object = $client;
        } elseif ($entity instanceof Company) {
            $company = $entity;
            $settings_object = $company;
        } elseif ($entity instanceof Vendor) {
            $vendor = $entity;
            $company = $entity->company;
            $settings_object = $company;
        } elseif ($entity instanceof Invoice || $entity instanceof Credit || $entity instanceof Quote) {
            $client = $entity->client;
            $company = $entity->company;
            $settings_object = $entity->client;
        } elseif ($entity instanceof PurchaseOrder) {
            $vendor = $entity->vendor;
            $company = $entity->company;
            $settings_object = $company;
        }

        $errors = [];

        foreach ($this->company_settings_fields as $field) {

            if ($this->validString($settings_object->getSetting($field))) {
                continue;
            }

            $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];

        }

        //test legal entity id present
        if (intval($company->legal_entity_id) == 0) {
            $errors[] = ['field' => "You have not registered a legal entity id as yet."];
        }

        //If not an individual, you MUST have a VAT number
        if ($company->getSetting('classification') != 'individual' && !$this->validString($company->getSetting('vat_number'))) {
            $errors[] = ['field' => 'vat_number', 'label' => ctrans("texts.vat_number")];
        } 
        
        // elseif ($company->getSetting('classification') == 'individual' && !$this->validString($company->getSetting('id_number'))) {
        //     $errors[] = ['field' => 'id_number', 'label' => ctrans("texts.id_number")];
        // }


        // foreach($this->company_fields as $field)
        // {

        // }

        return $errors;

    }

    // private function testInvoiceState($entity): array
    // {
    //     $errors = [];

    //     foreach($this->invoice_fields as $field)
    //     {

    //     }

    //     return $errors;
    // }

    // private function testVendorState(): array
    // {

    // }


    /************************************ helpers ************************************/
    private function validString(?string $string): bool
    {
        return iconv_strlen($string ?? '') >= 1;
    }

    private function checkNexus(Client $client): self
    {

        $company_country_code = $client->company->country()->iso_3166_2;
        $client_country_code = $client->country->iso_3166_2;
        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_countries = $br->eu_country_codes;

        if ($client_country_code == $company_country_code) {
        } elseif (in_array($company_country_code, $eu_countries) && !in_array($client_country_code, $eu_countries)) {
        } elseif (in_array($client_country_code, $eu_countries)) {

            // First, determine if we're over threshold
            $is_over_threshold = isset($client->company->tax_data->regions->EU->has_sales_above_threshold)
                               && $client->company->tax_data->regions->EU->has_sales_above_threshold;

            // Is this B2B or B2C?
            $is_b2c = strlen($client->vat_number ?? '') < 2
                    || !($client->has_valid_vat_number ?? false)
                    || $client->classification == 'individual';

            // B2C, under threshold, no Company VAT Registerd - must charge origin country VAT
            if ($is_b2c && !$is_over_threshold && strlen($client->company->settings->vat_number ?? '') < 2) {

            } elseif ($is_b2c) {
                if ($is_over_threshold) {
                    // B2C over threshold - need destination VAT number
                    if (!isset($client->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number)) {
                        $this->errors['invoice'][] = "Tax Nexus is client country ({$client_country_code}) - however VAT number not present for this region.";
                    }
                }

            } elseif ($is_over_threshold && !in_array($company_country_code, $eu_countries)) {

            }


        }

        return $this;
    }


}
