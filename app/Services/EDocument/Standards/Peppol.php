<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use InvoiceNinja\EInvoice\EInvoice;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Helpers\Invoice\Taxer;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\Address;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use App\Services\EDocument\Standards\Settings\PropertyResolver;
use App\Utils\Traits\NumberFormatter;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PartyNameType\PartyName;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotal as PeppolTaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxScheme as PeppolTaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\Party as PeppolParty;
use InvoiceNinja\EInvoice\Models\Peppol\PartyIdentification;

class Peppol extends AbstractService
{
    use Taxer;
    use NumberFormatter;
    
    /**
     * used as a proxy for 
     * the schemeID of partyidentification
     * property - for Storecove only:
     * 
     * Used in the format key:value
     * 
     * ie. IT:IVA / DE:VAT
     * 
     * Note there are multiple options for the following countries:
     * 
     * US (EIN/SSN) employer identification number / social security number
     * IT (CF/IVA) Codice Fiscale (person/company identifier) / company vat number
     *
     * @var array
     */
    private array $schemeIdIdentifiers = [
        'US' => 'EIN', 
        'US' => 'SSN',
        'NZ' => 'GST',
        'CH' => 'VAT', // VAT number = CHE - 999999999 - MWST|IVA|VAT
        'IS' => 'VAT',
        'LI' => 'VAT',
        'NO' => 'VAT',
        'AD' => 'VAT',
        'AL' => 'VAT',
        'AT' => 'VAT',
        'BA' => 'VAT',
        'BE' => 'VAT',
        'BG' => 'VAT',
        'AU' => 'ABN', //Australia	
        'CA' => 'CBN', //Canada
        'MX' => 'RFC', //Mexico
        'NZ' => 'GST', //Nuuu zulund
        'GB' => 'VAT', //Great Britain
        'SA' => 'TIN', //South Africa
        'CY' => 'VAT',
        'CZ' => 'VAT',
        'DE' => 'VAT', //tested - requires Payment Means to be defined.
        'DK' => 'ERST',
        'EE' => 'VAT',
        'ES' => 'VAT',
        'FI' => 'VAT',
        'FR' => 'VAT',
        'GR' => 'VAT',
        'HR' => 'VAT',
        'HU' => 'VAT',
        'IE' => 'VAT',
        'IT' => 'IVA', //tested - Requires a Customer Party Identification (VAT number)
        'IT' => 'CF', //tested - Requires a Customer Party Identification (VAT number)
        'LT' => 'VAT',
        'LU' => 'VAT',
        'LV' => 'VAT',
        'MC' => 'VAT',
        'ME' => 'VAT',
        'MK' => 'VAT',
        'MT' => 'VAT',
        'NL' => 'VAT',
        'PL' => 'VAT',
        'PT' => 'VAT',
        'RO' => 'VAT',
        'RS' => 'VAT',
        'SE' => 'VAT',
        'SI' => 'VAT',
        'SK' => 'VAT',
        'SM' => 'VAT',
        'TR' => 'VAT',
        'VA' => 'VAT',
        'IN' => 'GSTIN',
        'JP' => 'IIN',
        'MY' => 'TIN',
        'SG' => 'GST',
        'GB' => 'VAT',
        'SA' => 'TIN',
    ];

    private array $InvoiceTypeCodes = [
        "380" => "Commercial invoice",
        "381" => "Credit note",
        "383" => "Corrected invoice",
        "384" => "Prepayment invoice",
        "386" => "Proforma invoice",
        "875" => "Self-billed invoice",
        "976" => "Factored invoice",
        "84" => "Invoice for cross border services",
        "82" => "Simplified invoice",
        "80" => "Debit note",
        "875" => "Self-billed credit note",
        "896" => "Debit note related to self-billed invoice"
    ];

    private Company $company;

    private InvoiceSum | InvoiceSumInclusive $calc;

    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;
    /**
    * @param Invoice $invoice
    */
    public function __construct(public Invoice $invoice)
    {
        $this->company = $invoice->company;
        $this->calc = $this->invoice->calc();
        $this->setInvoice();
    }

    private function setInvoice(): self
    {

        
        if($this->invoice->e_invoice){

            
            $e = new EInvoice();
            $this->p_invoice = $e->decode('Peppol', json_encode($this->invoice->e_invoice->Invoice), 'json');

            return $this;

        }

        $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $this->setInvoiceDefaults();

        return $this;
    }

    public function getInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice
    {
        //@todo - need to process this and remove null values
        return $this->p_invoice;

    }

    public function toXml(): string
    {
        $e = new EInvoice();
        $xml = $e->encode($this->p_invoice, 'xml');

        $prefix = '<?xml version="1.0" encoding="utf-8"?>
    <Invoice
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';

        return str_ireplace(['\n','<?xml version="1.0"?>'], ['', $prefix], $xml);

    }

    public function toJson(): string
    {
        $e = new EInvoice();
        $json =  $e->encode($this->p_invoice, 'json');

        return $json;
        // $prefixes =  str_ireplace(["cac:","cbc:"], "", $json);
        // return str_ireplace(["InvoiceLine", "PostalAddress", "PartyName"], ["invoiceLines","address", "companyName"], $prefixes);
    }

    public function toArray(): array
    {
        return json_decode($this->toJson(), true);
    }

    public function run()
    {
        $this->p_invoice->ID = $this->invoice->number;
        $this->p_invoice->IssueDate = new \DateTime($this->invoice->date);
        $this->p_invoice->InvoiceTypeCode = 380; //
        $this->p_invoice->AccountingSupplierParty = $this->getAccountingSupplierParty();
        $this->p_invoice->AccountingCustomerParty = $this->getAccountingCustomerParty();
        $this->p_invoice->InvoiceLine = $this->getInvoiceLines();
        
        $this->p_invoice->TaxTotal = $this->getTotalTaxes();
        $this->p_invoice->LegalMonetaryTotal = $this->getLegalMonetaryTotal();

        $this->countryLevelMutators();
        
        return $this;

    }

    private function getLegalMonetaryTotal(): LegalMonetaryTotal
    {
        $taxable = $this->getTaxable();

        $lmt = new LegalMonetaryTotal();

        $lea = new LineExtensionAmount();
        $lea->currencyID = $this->invoice->client->currency()->code;
        $lea->amount = $this->invoice->uses_inclusive_taxes ? round($this->invoice->amount - $this->invoice->total_taxes, 2) : $taxable;
        $lmt->LineExtensionAmount = $lea;

        $tea = new TaxExclusiveAmount();
        $tea->currencyID = $this->invoice->client->currency()->code;
        $tea->amount = $this->invoice->uses_inclusive_taxes ? round($this->invoice->amount - $this->invoice->total_taxes,2) : $taxable;
        $lmt->TaxExclusiveAmount = $tea;

        $tia = new TaxInclusiveAmount();
        $tia->currencyID = $this->invoice->client->currency()->code;
        $tia->amount = $this->invoice->amount;
        $lmt->TaxInclusiveAmount = $tia;

        $pa = new PayableAmount();
        $pa->currencyID = $this->invoice->client->currency()->code;
        $pa->amount = $this->invoice->amount;
        $lmt->PayableAmount = $pa;

        return $lmt;
    }

    private function getTotalTaxAmount(): float
    {
        if(!$this->invoice->total_taxes)
            return 0;
        elseif($this->invoice->uses_inclusive_taxes)
            return $this->invoice->total_taxes;
        
        return $this->calcAmountLineTax($this->invoice->tax_rate1, $this->invoice->amount) ?? 0;
    }

    private function getTotalTaxes(): array
    {
        $taxes = [];

        $type_id = $this->invoice->line_items[0]->type_id;

        // if(strlen($this->invoice->tax_name1 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = $this->getTotalTaxAmount();

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->invoice->amount - $this->invoice->total_taxes : $this->invoice->amount;
            $tax_subtotal->TaxableAmount = $taxable_amount;

            $tc = new TaxCategory();
            $tc->ID = $type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $this->invoice->tax_rate1;
            $ts = new PeppolTaxScheme();
            $ts->ID = strlen($this->invoice->tax_name1 ?? '') > 1 ? $this->invoice->tax_name1 : '0'; 
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;

            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $taxes[] = $tax_total;
        // }


        if(strlen($this->invoice->tax_name2 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;

            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($this->invoice->tax_rate2, $this->invoice->amount) : $this->calcAmountLineTax($this->invoice->tax_rate2, $this->invoice->amount);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->invoice->amount- $this->invoice->total_taxes : $this->invoice->amount;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $this->invoice->tax_rate2;
            $ts = new PeppolTaxScheme();
            $ts->ID = $this->invoice->tax_name2;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal = $tax_subtotal;

            $taxes[] = $tax_total;

        }

        if(strlen($this->invoice->tax_name3 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($this->invoice->tax_rate3, $this->invoice->amount) : $this->calcAmountLineTax($this->invoice->tax_rate3, $this->invoice->amount);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->invoice->amount - $this->invoice->total_taxes : $this->invoice->amount;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $this->invoice->tax_rate3;
            $ts = new PeppolTaxScheme();
            $ts->ID = $this->invoice->tax_name3;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal = $tax_subtotal;

            $taxes[] = $tax_total;

        }


        return $taxes;
    }

    private function getInvoiceLines(): array
    {
        $lines = [];

        foreach($this->invoice->line_items as $key => $item) {

            $_item = new Item();
            $_item->Name = $item->product_key;
            $_item->Description = $item->notes;

            $line = new InvoiceLine();
            $line->ID = $key + 1;
            $line->InvoicedQuantity = $item->quantity;

            $lea = new LineExtensionAmount();
            $lea->currencyID = $this->invoice->client->currency()->code;
            // $lea->amount = $item->line_total;
            $lea->amount = $this->invoice->uses_inclusive_taxes ? $item->line_total - $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) : $item->line_total;
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            $item_taxes = $this->getItemTaxes($item);

            if(count($item_taxes) > 0) {
                $line->TaxTotal = $item_taxes;
            }
            // else {
            //     $line->TaxTotal = $this->zeroTaxAmount();
            // }

            $price = new Price();
            $pa = new PriceAmount();
            $pa->currencyID = $this->invoice->client->currency()->code;
            $pa->amount = $this->costWithDiscount($item) - ( $this->invoice->uses_inclusive_taxes ? ($this->calcInclusiveLineTax($item->tax_rate1, $item->line_total)/$item->quantity) : 0);
            $price->PriceAmount = $pa;

            $line->Price = $price;

            $lines[] = $line;
        }

        return $lines;
    }

    private function costWithDiscount($item)
    {
        $cost = $item->cost;

        if ($item->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                $cost -= $item->discount / $item->quantity;
            } else {
                $cost -= $cost * $item->discount / 100;
            }
        }

        return $cost;
    }

    private function zeroTaxAmount(): array
    {
        $blank_tax = [];

        $tax_amount = new TaxAmount();
        $tax_amount->currencyID = $this->invoice->client->currency()->code;
        $tax_amount->amount = '0';
        $tax_subtotal = new TaxSubtotal();
        $tax_subtotal->TaxAmount = $tax_amount;

        $taxable_amount = new TaxableAmount();
        $taxable_amount->currencyID = $this->invoice->client->currency()->code;
        $taxable_amount->amount = '0';
        $tax_subtotal->TaxableAmount = $taxable_amount;
        $tc = new TaxCategory();
        $tc->ID = 'Z';
        $tc->Percent = 0;
        $ts = new PeppolTaxScheme();
        $ts->ID = '0';
        $tc->TaxScheme = $ts;
        $tax_subtotal->TaxCategory = $tc;

        $tax_total = new TaxTotal();
        $tax_total->TaxAmount = $tax_amount;
        $tax_total->TaxSubtotal[] = $tax_subtotal;
        $blank_tax[] = $tax_total;


        return $blank_tax;
    }

    private function getItemTaxes(object $item): array
    {
        $item_taxes = [];

        if(strlen($item->tax_name1 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) : $this->calcAmountLineTax($item->tax_rate1, $item->line_total);
            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $item->line_total - $tax_amount->amount : $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;
            $tc = new TaxCategory();
            $tc->ID = $item->type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $item->tax_rate1;
            $ts = new PeppolTaxScheme();
            $ts->ID = $item->tax_name1;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;
            $item_taxes[] = $tax_total;

        }


        if(strlen($item->tax_name2 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            
            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate2, $item->line_total) : $this->calcAmountLineTax($item->tax_rate2, $item->line_total);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $item->type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $item->tax_rate2;
            $ts = new PeppolTaxScheme();
            $ts->ID = $item->tax_name2;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;
            $item_taxes[] = $tax_total;


        }


        if(strlen($item->tax_name3 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;

            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate3, $item->line_total) : $this->calcAmountLineTax($item->tax_rate3, $item->line_total);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $item->type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $item->tax_rate3;
            $ts = new PeppolTaxScheme();
            $ts->ID = $item->tax_name3;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;

            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;
            $item_taxes[] = $tax_total;


        }

        return $item_taxes;
    }

    private function getAccountingSupplierParty(): AccountingSupplierParty
    {

        $asp = new AccountingSupplierParty();

        $party = new Party();
        $party_name = new PartyName();
        $party_name->Name = $this->invoice->company->present()->name();
        $party->PartyName[] = $party_name;

        $address = new Address();
        $address->CityName = $this->invoice->company->settings->city;
        $address->StreetName = $this->invoice->company->settings->address1;
        // $address->BuildingName = $this->invoice->company->settings->address2;
        $address->PostalZone = $this->invoice->company->settings->postal_code;
        $address->CountrySubentity = $this->invoice->company->settings->state;
        // $address->CountrySubentityCode = $this->invoice->company->settings->state;

        $country = new Country();
        $country->IdentificationCode = $this->invoice->company->country()->iso_3166_2;
        $address->Country = $country;

        $party->PostalAddress = $address;
        $party->PhysicalLocation = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $this->getSetting('Invoice.AccountingSupplierParty.Party.Contact') ?? $this->invoice->company->owner()->present()->email();
        $contact->Telephone = $this->getSetting('Invoice.AccountingSupplierParty.Party.Telephone') ?? $this->invoice->company->getSetting('phone');
        $contact->Name = $this->getSetting('Invoice.AccountingSupplierParty.Party.Name') ?? $this->invoice->company->owner()->present()->name();

        $party->Contact = $contact;

        $asp->Party = $party;

        return $asp;
    }

    private function getAccountingCustomerParty(): AccountingCustomerParty
    {

        $acp = new AccountingCustomerParty();

        $party = new Party();

        if(strlen($this->invoice->client->vat_number ?? '') > 1) {
            
            $pi = new PartyIdentification;
            $vatID = new ID;
            $vatID->schemeID = 'CH:MWST';
            $vatID->value = $this->invoice->client->vat_number;
 
            $pi->ID = $vatID;

            $party->PartyIdentification[] = $pi;

        }

        $party_name = new PartyName();
        $party_name->Name = $this->invoice->client->present()->name();
        $party->PartyName[] = $party_name;

        $address = new Address();
        $address->CityName = $this->invoice->client->city;
        $address->StreetName = $this->invoice->client->address1;
        // $address->BuildingName = $this->invoice->client->address2;
        $address->PostalZone = $this->invoice->client->postal_code;
        $address->CountrySubentity = $this->invoice->client->state;
        // $address->CountrySubentityCode = $this->invoice->client->state;


        $country = new Country();
        $country->IdentificationCode = $this->invoice->client->country->iso_3166_2;
        $address->Country = $country;

        $party->PostalAddress = $address;
        $party->PhysicalLocation = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $this->invoice->client->present()->email();

        $party->Contact = $contact;

        $acp->Party = $party;

        return $acp;
    }

    private function getTaxable(): float
    {
        $total = 0;

        foreach ($this->invoice->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($this->invoice->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        if ($this->invoice->discount > 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $this->invoice->discount;
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
                $total = round($total, 2);
            }
        }

        if ($this->invoice->custom_surcharge1 && $this->invoice->custom_surcharge_tax1) {
            $total += $this->invoice->custom_surcharge1;
        }

        if ($this->invoice->custom_surcharge2 && $this->invoice->custom_surcharge_tax2) {
            $total += $this->invoice->custom_surcharge2;
        }

        if ($this->invoice->custom_surcharge3 && $this->invoice->custom_surcharge_tax3) {
            $total += $this->invoice->custom_surcharge3;
        }

        if ($this->invoice->custom_surcharge4 && $this->invoice->custom_surcharge_tax4) {
            $total += $this->invoice->custom_surcharge4;
        }

        return $total;
    }

    public function setInvoiceDefaults(): self
    {
        $settings = [
            'AccountingCostCode' => 7,
            'AccountingCost' => 7,
            'BuyerReference' => 6,
            'AccountingSupplierParty' => 1,
            'AccountingCustomerParty' => 2,
            'PayeeParty' => 1,
            'BuyerCustomerParty' => 2,
            'SellerSupplierParty' => 1,
            'TaxRepresentativeParty' => 1,
            'Delivery' => 1,
            'DeliveryTerms' => 7,
            'PaymentMeans' => 7,
            'PaymentTerms' => 7,
        ];

        //only scans for top level props
        foreach($settings as $prop => $visibility){

            if($prop_value = $this->getSetting($prop))        
                $this->p_invoice->{$prop} = $prop_value;

        }

        return $this;
    }

    public function getSetting(string $property_path): mixed
    {
    
        if($prop_value = PropertyResolver::resolve($this->invoice->e_invoice, $property_path)) 
            return $prop_value;
        elseif($prop_value = PropertyResolver::resolve($this->invoice->client->e_invoice, $property_path)) 
            return $prop_value;
        elseif($prop_value = PropertyResolver::resolve($this->invoice->company->e_invoice, $property_path)) 
            return $prop_value;
        
        return null;

    }

    public function countryLevelMutators():self
    {

        if(method_exists($this, $this->invoice->company->country()->iso_3166_2))
            $this->{$this->invoice->company->country()->iso_3166_2}();

        return $this;
    }

    private function setPaymentMeans(bool $required = false): self
    {
       
        if($this->p_invoice->PaymentMeans)
            return $this;
        elseif(!isset($this->p_invoice->PaymentMeans) && $paymentMeans = $this->getSetting('Invoice.PaymentMeans')){
            $this->p_invoice->PaymentMeans = is_array($paymentMeans) ? $paymentMeans : [$paymentMeans];
            return $this;
        }

        if($required)
            throw new \Exception('e-invoice generation halted:: Payment Means required');

        return $this;
    }

    private function DE(): self
    {
        // accountingsupplierparty.party.contact MUST be set - Name / Telephone / Electronic Mail
        // this is forced by default.
        
        $this->setPaymentMeans(true);

        return $this;
    }

    private function CH(): self
    {
        //if QR-Bill support required - then special flow required.... optional.

        return $this;
    }

    private function AT(): self
    {
        //special fields for sending to AT:GOV
        return $this;
    }

    private function AU(): self
    {

        //if payment means are included, they must be the same `type`
        return $this;
    }

    private function ES(): self
    {

    // For B2B, provide an ES:DIRE routing identifier and an ES:VAT tax identifier. 
    // both sender and receiver must be an ES company;
    // you must have a "credit_transfer" PaymentMean;
    // the "dueDate" property is mandatory.

// For B2G, provide three ES:FACE identifiers in the routing object, 
// as well as the ES:VAT tax identifier in the accountingCustomerParty.publicIdentifiers. 
// The invoice will then be routed through the FACe network. The three required ES:FACE identifiers are as follows:
//   "routing": {
//     "eIdentifiers":[
//       {
//         "scheme": "ES:FACE",
//         "id": "L01234567",
//         "role": "ES-01-FISCAL"
//       },
//       {
//         "scheme": "ES:FACE",
//         "id": "L01234567",
//         "role": "ES-02-RECEPTOR"
//       },
//       {
//         "scheme": "ES:FACE",
//         "id": "L01234567",
//         "role": "ES-03-PAGADOR"
//       }
//     ]
//   }

        return $this;
    }

    private function FI(): self
    {

        // For Finvoice, provide an FI:OPID routing identifier and an FI:OVT legal identifier. 
        // An FI:VAT is recommended. In many cases (depending on the sender/receiver country and the type of service/goods) 
        // an FI:VAT is required. So we recommend always including this.

        return $this;
    }

    private function FR(): self
    {
        // When sending invoices to the French government (Chorus Pro):

        // All invoices have to be routed to SIRET 0009:11000201100044. There is no test environment for sending to public entities.

        // The SIRET / 0009 identifier of the final recipient is to be included in the invoice.accountingCustomerParty.publicIdentifiers array.

        // The service code must be sent in invoice.buyerReference (deprecated) or the invoice.references array (documentType buyer_reference)

        // The commitment number must be sent in the invoice.orderReference (deprecated) or the invoice.references array (documentType purchase_order).

        // Invoices to companies (SIRET / 0009 or SIRENE / 0002) are routed directly to that identifier.
        return $this;
    }

    private function IT(): self
    {
        // IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)

        // IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and the receiver IT:CUUO (codice destinatario)

        // IT Sender, non-IT Receiver
        // Provide the receiver tax identifier and any routing identifier applicable to the receiving country (see Receiver Identifiers).

        // non-IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)

        // non-IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and an optional email. The invoice will be eReported and sent via email. Note that this cannot be a PEC email address.

        return $this;
    }

    private function MY(): self
    {
        //way too much to digest here, delayed.
        return $this;
    }

    private function NL(): self
    {

        // When sending to public entities, the invoice.accountingSupplierParty.party.contact.email is mandatory.

        // Dutch senders and receivers require a legal identifier. For companies, this is NL:KVK, for public entities this is NL:OINO.

        return $this;
    }

    private function NZ(): self
    {
        // New Zealand uses a GLN to identify businesses. In addition, when sending invoices to a New Zealand customer, make sure you include the pseudo identifier NZ:GST as their tax identifier.
        return $this;
    }

    private function PL(): self
    {

        // Because using this network is not yet mandatory, the default workflow is to not use this network. Therefore, you have to force its use, as follows:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "PL:VAT",
        //         "id": "PL0101010101"
        //     }
        //   ],
        //   "networks": [
        //     {
        //       "application": "pl-ksef",
        //       "settings": {
        //         "enabled": true
        //       }
        //     }
        //   ]
        // }
        // Note this will only work if your LegalEntity has been setup for this network. 

        return $this;
    }

    private function RO(): self
    {
    // Because using this network is not yet mandatory, the default workflow is to not use this network. Therefore, you have to force its use, as follows:

    // "routing": {
    // "eIdentifiers": [
    //     {
    //         "scheme": "RO:VAT",
    //         "id": "RO010101010"
    //     }
    // ],
    // "networks": [
    //     {
    //     "application": "ro-anaf",
    //     "settings": {
    //         "enabled": true
    //     }
    //     }
    // ]
    // }
    // Note this will only work if your LegalEntity has been setup for this network.
    // The county field for a Romania address must use the ISO3166-2:RO codes, e.g. "RO-AB, RO-AR". Don’t omit the country prefix!
    // The city field for county RO-B must be SECTOR1 - SECTOR6.

        return $this;
    }

    private function SG(): self
    {
        //delayed  - stage 2
        return $this;
    }

    //Sweden
    private function SE(): self
    {
        // Deliver invoices to the "Svefaktura" co-operation of local Swedish service providers. 
        // Routing is through the SE:ORGNR together with a network specification:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "SE:ORGNR",
        //         "id": "0012345678"
        //     }
        //   ],
        //   "networks": [
        //     {
        //       "application": "svefaktura",
        //       "settings": {
        //         "enabled": true
        //       }
        //     }
        //   ]
        // }
        // Use of the "Svefaktura" co-operation can also be induced by specifying an operator id, as follows:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "SE:ORGNR",
        //         "id": "0012345678"
        //     },
        //     {
        //         "scheme": "SE:OPID",
        //         "id": "1234567890"
        //     }
        //   ]
        // }
        return $this;
    }
}
