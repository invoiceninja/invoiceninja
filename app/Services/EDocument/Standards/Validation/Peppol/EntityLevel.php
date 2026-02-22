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

use XSLTProcessor;
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
        'AT' => '/^(AT)?U\d{9}$/i', // Austria: U + 9 digits
        'BE' => '/^(BE)?0\d{9}$/i', // Belgium: 0 + 9 digits
        'BG' => '/^(BG)?\d{9,10}$/i', // Bulgaria: 9-10 digits
        'CY' => '/^(CY)?\d{8}[A-Z]$/i', // Cyprus: 8 digits + 1 letter
        'CZ' => '/^(CZ)?\d{8,10}$/i', // Czech Republic: 8-10 digits
        'DE' => '/^(DE)?\d{9}$/i', // Germany: 9 digits
        'DK' => '/^(DK)?\d{8}$/i', // Denmark: 8 digits
        'EE' => '/^(EE)?\d{9}$/i', // Estonia: 9 digits
        'ES' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i', // Spain: 1 alphanumeric + 7 digits + 1 alphanumeric
        'ES-CN' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i', // Canary Islands: Same as Spain
        'ES-CE' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i', // Ceuta: Same as Spain
        'ES-ML' => '/^(ES)?[A-Z0-9]\d{7}[A-Z0-9]$/i', // Melilla: Same as Spain
        'FI' => '/^(FI)?\d{8}$/i', // Finland: 8 digits
        'FR' => '/^(FR)?[A-HJ-NP-Z0-9]{2}\d{9}$/i', // France: 2 alphanumeric (excluding I, O, Q) + 9 digits
        'GR' => '/^(GR|EL)?\d{9}$/i', // Greece: 9 digits (can use GR or EL prefix)
        'HR' => '/^(HR)?\d{11}$/i', // Croatia: 11 digits
        'HU' => '/^(HU)?\d{8}$/i', // Hungary: 8 digits
        'IE' => '/^(IE)?\d[A-Z0-9\+\*]\d{5}[A-Z]{1,2}$/i', // Ireland: 1 digit + 1 alphanumeric + 5 digits + 1-2 letters
        'IT' => '/^(IT)?\d{11}$/i', // Italy: 11 digits
        'LT' => '/^(LT)?(\d{9}|\d{12})$/i', // Lithuania: 9 or 12 digits
        'LU' => '/^(LU)?\d{8}$/i', // Luxembourg: 8 digits
        'LV' => '/^(LV)?\d{11}$/i', // Latvia: 11 digits
        'MT' => '/^(MT)?\d{8}$/i', // Malta: 8 digits
        'NL' => '/^(NL)?\d{9}B\d{2}$/i', // Netherlands: 9 digits + B + 2 digits
        'PL' => '/^(PL)?\d{10}$/i', // Poland: 10 digits
        'PT' => '/^(PT)?\d{9}$/i', // Portugal: 9 digits
        'RO' => '/^(RO)?\d{2,10}$/i', // Romania: 2-10 digits
        'SE' => '/^(SE)?\d{12}$/i', // Sweden: 12 digits
        'SI' => '/^(SI)?\d{8}$/i', // Slovenia: 8 digits
        'SK' => '/^(SK)?\d{10}$/i', // Slovakia: 10 digits
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

        //If not an individual, you MUST have a VAT number if you are in the EU
        if (!in_array($client->classification, ['government', 'individual']) && in_array($client->country->iso_3166_2, $this->eu_country_codes)) {
            if (!$this->validString($client->vat_number)) {
                $errors[] = ['field' => 'vat_number', 'label' => ctrans("texts.vat_number")];
            } elseif (isset($this->vat_number_regex[$client->country->iso_3166_2]) && !preg_match($this->vat_number_regex[$client->country->iso_3166_2], str_replace([" ",".","-"], "", $client->vat_number))) {
                $errors[] = ['field' => 'vat_number', 'label' => ctrans("texts.invalid_vat_number")];
            }
        }



        //Primary contact email is present.
        if ($client->present()->email() == 'No Email Set') {
            $errors[] = ['field' => 'email', 'label' => ctrans("texts.email")];
        }

        $delivery_network_supported = $client->checkDeliveryNetwork();

        if (is_string($delivery_network_supported)) {
            $errors[] = ['field' => ctrans("texts.country"), 'label' => $delivery_network_supported];
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
        } elseif ($company->getSetting('classification') == 'individual' && !$this->validString($company->getSetting('id_number'))) {
            $errors[] = ['field' => 'id_number', 'label' => ctrans("texts.id_number")];
        }


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
