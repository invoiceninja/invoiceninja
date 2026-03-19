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

use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\Address;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\PartyIdentification;
use InvoiceNinja\EInvoice\Models\Peppol\PartyNameType\PartyName;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\IdentificationCode;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use App\Services\EDocument\Standards\Peppol;

class PeppolPartyBuilder
{
    public function __construct(private Peppol $peppol)
    {
    }

    /**
     * getAccountingSupplierParty
     *
     * @return AccountingSupplierParty
     */
    public function getAccountingSupplierParty(): AccountingSupplierParty
    {
        $invoice = $this->peppol->getInvoiceModel();
        $company = $this->peppol->getCompany();
        $gateway = $this->peppol->getGateway();
        $taxCalculator = $this->peppol->getTaxCalculator();

        $asp = new AccountingSupplierParty();

        $party = new Party();
        $party_name = new PartyName();
        $party_name->Name = $invoice->company->present()->name();
        $party->PartyName[] = $party_name;


        if (strlen($company->settings->vat_number ?? '') > 1) {

            $pi = new PartyIdentification();
            $vatID = new ID();
            $vatID->schemeID = $this->resolveScheme();
            $vatID->value = strlen($this->peppol->getOverrideVatNumber() ?? '') > 1 ? $this->peppol->getOverrideVatNumber() : preg_replace("/[^a-zA-Z0-9]/", "", $invoice->company->settings->vat_number); //todo if we are cross border - switch to the supplier local vat number

            $pi->ID = $vatID;
            $party->PartyIdentification[] = $pi;

            $companyID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CompanyID();

            $pts = new \InvoiceNinja\EInvoice\Models\Peppol\PartyTaxSchemeType\PartyTaxScheme();
            $companyID->value = strlen($this->peppol->getOverrideVatNumber() ?? '') > 1 ? $this->peppol->getOverrideVatNumber() : preg_replace("/[^a-zA-Z0-9]/", "", $invoice->company->settings->vat_number); //todo if we are cross border - switch to the supplier local vat number
            $pts->CompanyID = $companyID;

            $ts = new TaxScheme();
            $id = new ID();
            $id->value = $taxCalculator->standardizeTaxSchemeId('vat');
            $ts->ID = $id;
            $pts->TaxScheme = $ts;

            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\EndpointID();
            $id->value = preg_replace("/[^a-zA-Z0-9]/", "", $company->settings->vat_number);
            $id->schemeID = $this->resolveScheme();
            $party->EndpointID = $id;
            $party->PartyTaxScheme[] = $pts;
        }

if (strlen($company->settings->vat_number ?? '') <= 1 && strlen($this->peppol->getOverrideVatNumber() ?? '') <= 1) {

    $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\EndpointID();
    $scheme_parts = explode(':', $company->settings->id_number ?? '');

    if(count($scheme_parts) === 2) {
        $id->schemeID = $scheme_parts[0];
        $id->value = $scheme_parts[1];
    }
    else {
        $id->schemeID = $this->resolveScheme();
        $id->value = preg_replace("/[^a-zA-Z0-9]/", "", $company->settings->id_number ?? '');
    }

    $party->EndpointID = $id;
}


        $address = new Address();
        $address->CityName = $invoice->company->settings->city;
        $address->StreetName = $invoice->company->settings->address1;

        if (strlen($invoice->company->settings->address2 ?? '') > 1) {
            $address->AdditionalStreetName = $invoice->company->settings->address2;
        }

        $address->PostalZone = $invoice->company->settings->postal_code;
        // $address->CountrySubentity = $invoice->company->settings->state;

        $country = new Country();

        $ic = new IdentificationCode();
        $ic->value = substr($invoice->company->country()->iso_3166_2, 0, 2);
        $country->IdentificationCode = $ic;

        $address->Country = $country;
        $party->PostalAddress = $address;

        /** we must have a valid contact name, a blank string does not work, so we need to fall back to the company name here as a safety fallback */
        $contact_name = strlen($invoice->company->owner()->present()->name() ?? '') > 2 ? $invoice->company->owner()->present()->name() : $invoice->company->present()->name();

        $contact = new Contact();
        $contact->ElectronicMail = $gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Contact') ?? $invoice->company->owner()->present()->email();
        $contact->Telephone = $gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Telephone') ?? $invoice->company->getSetting('phone');
        $contact->Name = $gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Name') ?? $contact_name;

        $party->Contact = $contact;

        $ple = new \InvoiceNinja\EInvoice\Models\Peppol\PartyLegalEntity();
        $ple->RegistrationName = $invoice->company->present()->name();

// if no vat number, we should inject the id_number as the company identifier!
if (strlen($company->settings->vat_number ?? '') <= 1
    && strlen($this->peppol->getOverrideVatNumber() ?? '') <= 1
    && strlen($company->settings->id_number ?? '') > 1)
{
    $companyID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CompanyID();
    $companyID->value = preg_replace("/[^a-zA-Z0-9]/", "", $company->settings->id_number);
    $ple->CompanyID = $companyID;
}


        $party->PartyLegalEntity[] = $ple;

        $asp->Party = $party;

        return $asp;
    }

    /**
     * getAccountingCustomerParty
     *
     * @return AccountingCustomerParty
     */
    public function getAccountingCustomerParty(): AccountingCustomerParty
    {
        $invoice = $this->peppol->getInvoiceModel();
        $taxCalculator = $this->peppol->getTaxCalculator();

        $acp = new AccountingCustomerParty();

        $party = new Party();

        if (strlen($invoice->client->vat_number ?? '') > 1) {

            $pi = new PartyIdentification();

            $vatID = new ID();
            $vatID->schemeID = $this->resolveScheme(true);
            $vatID->value = preg_replace("/[^a-zA-Z0-9]/", "", $invoice->client->vat_number);
            $pi->ID = $vatID;

            $party->PartyIdentification[] = $pi;

            // Do Not Show The Customer VAT number if the tax category is not subject to VAT
            if ($this->peppol->getTaxCategoryId() !== 'O') {
            //// If this is intracommunity supply, ensure that the country prefix is on the party tax scheme
                $pts = new \InvoiceNinja\EInvoice\Models\Peppol\PartyTaxSchemeType\PartyTaxScheme();
                $companyID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CompanyID();
                $companyID->value = $this->ensureVatNumberPrefix($invoice->client->vat_number, $invoice->client->country->iso_3166_2);
                $pts->CompanyID = $companyID;
                //// If this is intracommunity supply, ensure that the country prefix is on the party tax scheme

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $taxCalculator->standardizeTaxSchemeId('vat');
                $ts->ID = $id;
                $pts->TaxScheme = $ts;

                $party->PartyTaxScheme[] = $pts;
            }
        }

        $party_name = new PartyName();
        $party_name->Name = $invoice->client->present()->name();

        //@todo if we have an exact GLN/routing number we should update this, otherwise Storecove will proxy and update on transit

        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\EndpointID();
        $id->value = $invoice->client->routing_id
        ?? preg_replace("/[^a-zA-Z0-9]/", "", $invoice->client->vat_number ?? '')
        ?? preg_replace("/[^a-zA-Z0-9]/", "", $invoice->client->id_number ?? '')
        ?? 'fallback1234';

        $id->schemeID = $this->resolveScheme(true);
        $party->EndpointID = $id;

        $party->PartyName[] = $party_name;

        $locationData = $invoice->service()->location();

        $address = new Address();
        $address->CityName = $locationData['city'];
        $address->StreetName = $locationData['address1'];

        if (strlen($locationData['address2'] ?? '') > 1) {
            $address->AdditionalStreetName = $locationData['address2'];
        }

        $address->PostalZone = $locationData['postal_code'];
        // $address->CountrySubentity = $invoice->client->state;

        $country = new Country();

        $ic = new IdentificationCode();
        $ic->value = substr($locationData['country_code'], 0, 2);

        $country->IdentificationCode = $ic;
        $address->Country = $country;

        $party->PostalAddress = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $invoice->client->present()->email();

        if (strlen($invoice->client->phone ?? '') > 2) {
            $contact->Telephone = $invoice->client->phone;
        }

        $party->Contact = $contact;

        $ple = new \InvoiceNinja\EInvoice\Models\Peppol\PartyLegalEntity();
        $ple->RegistrationName = $invoice->client->present()->name();
        $party->PartyLegalEntity[] = $ple;

        $acp->Party = $party;

        return $acp;
    }

    public function getDelivery(): array
    {
        $invoice = $this->peppol->getInvoiceModel();

        $locationData = $invoice->service()->location();
        $delivery = new \InvoiceNinja\EInvoice\Models\Peppol\DeliveryType\Delivery();
        $location = new \InvoiceNinja\EInvoice\Models\Peppol\LocationType\DeliveryLocation();

        $address = new Address();
        $address->CityName = $locationData['shipping_city'];
        $address->StreetName = $locationData['shipping_address1'];

        if (strlen($locationData['shipping_address2'] ?? '') > 1) {
            $address->AdditionalStreetName = $locationData['shipping_address2'];
        }

        $address->PostalZone = $locationData['shipping_postal_code'];

        $country = new Country();

        $ic = new IdentificationCode();
        $ic->value = substr($locationData['shipping_country_code'], 0, 2);
        $country->IdentificationCode = $ic;

        $ic = new IdentificationCode();
        $shipping = $locationData['shipping_country_code'];
        $ic->value = $shipping;

        $country->IdentificationCode = $ic;
        $address->Country = $country;
        $location->Address = $address;
        $delivery->DeliveryLocation = $location;

        // Safely extract delivery date using data_get to handle missing properties
        $delivery_date = data_get($invoice->e_invoice, 'Invoice.Delivery.0.ActualDeliveryDate.date')
            ?? data_get($invoice->e_invoice, 'Invoice.Delivery.0.ActualDeliveryDate')
            ?? null;

        if ($delivery_date) {
            $delivery->ActualDeliveryDate = new \DateTime($delivery_date);
        }

        return [$delivery];

    }

    /**
     * ResolveScheme
     *
     * If we need to explicitly set the schemeID, we will need to resolve
     * the exact one based on the type the user has, ie. GLN DUNS, validation
     * is performed here, so lots can go wrong if bad data is used.
     *
     * @param  bool $is_client
     * @return string
     */
    public function resolveScheme(bool $is_client = false): string
    {

        // $vat_number = $is_client ? preg_replace("/[^a-zA-Z0-9]/", "", $this->peppol->getInvoiceModel()->client->vat_number ?? '') : preg_replace("/[^a-zA-Z0-9]/", "", $this->peppol->getInvoiceModel()->company->settings->vat_number ?? '');
        // $tax_number = $is_client ? preg_replace("/[^a-zA-Z0-9]/", "", $this->peppol->getInvoiceModel()->client->id_number ?? '') : preg_replace("/[^a-zA-Z0-9]/", "", $this->peppol->getInvoiceModel()->company->settings->id_number ?? '');
        // $country_code = $is_client ? $this->peppol->getInvoiceModel()->client->country->iso_3166_2 : $this->peppol->getInvoiceModel()->company->country()->iso_3166_2;

        return '0037';
    }

    /**
     * Ensures the VAT number has the correct country code prefix.
     *
     * @param string $vatNumber The raw VAT number.
     * @param string $countryCode The 2-letter ISO country code.
     * @return string The formatted VAT number with prefix.
     */
    public function ensureVatNumberPrefix(string $vatNumber, string $countryCode): string
    {
        // Handle Greece special case
        $prefix = ($countryCode === 'GR') ? 'EL' : $countryCode;

        // Clean the VAT number by removing non-alphanumeric characters
        $cleanedVat = preg_replace("/[^a-zA-Z0-9]/", "", $vatNumber);

        // Check if the VAT number already starts with the country prefix
        // If it does, return it as-is (preserving any check digits like "AA" in "FRAA123456789")
        if (str_starts_with(strtoupper($cleanedVat), strtoupper($prefix))) {
            return $cleanedVat;
        }

        // If the prefix is missing, clean and prepend it
        return $prefix . $cleanedVat;
    }
}
