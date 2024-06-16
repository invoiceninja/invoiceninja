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

use App\Models\Invoice;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\Address;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\PartyNameType\PartyName;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxScheme as PeppolTaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotal as PeppolTaxTotal;

class Peppol extends AbstractService
{
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

    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;

    private InvoiceSum | InvoiceSumInclusive $calc;

    /**
     * @param Invoice $invoice
     */
    public function __construct(public Invoice $invoice)
    {
        $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $this->calc = $this->invoice->calc();
    }

    public function getInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice
    {
        //@todo - need to process this and remove null values
        return $this->p_invoice;

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

        // $payeeFinancialAccount = (new PayeeFinancialAccount())
        //     ->setBankId($company->settings->custom_value1)
        //     ->setBankName($company->settings->custom_value2);

        // $paymentMeans = (new PaymentMeans())
        // ->setPaymentMeansCode($invoice->custom_value1)
        // ->setPayeeFinancialAccount($payeeFinancialAccount);
        // $ubl_invoice->setPaymentMeans($paymentMeans);

    }

    private function getLegalMonetaryTotal(): LegalMonetaryTotal
    {
        $taxable = $this->getTaxable();

        $lmt = new LegalMonetaryTotal();

        $lea = new LineExtensionAmount();
        $lea->currencyID = $this->invoice->client->currency()->code;
        $lea->amount = $taxable;
        $lmt->LineExtensionAmount = $lea;

        $tea = new TaxExclusiveAmount();
        $tea->currencyID = $this->invoice->client->currency()->code;
        $tea->amount = $taxable;
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

    private function getTotalTaxes(): array
    {
        $taxes = [];

        $type_id = $this->invoice->line_items[0]->type_id;

        if(strlen($this->invoice->tax_name1 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = round($this->invoice->amount * (1 / $this->invoice->tax_rate1), 2);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;


            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->amount;
            $tax_subtotal->TaxableAmount = $taxable_amount;



            $tc = new TaxCategory();
            $tc->ID = $type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $this->invoice->tax_rate1;
            $ts = new PeppolTaxScheme();
            $ts->ID = $this->invoice->tax_name1;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;

            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal = $tax_subtotal;

            $taxes[] = $tax_total;
        }


        if(strlen($this->invoice->tax_name2 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = round($this->invoice->amount * (1 / $this->invoice->tax_rate2), 2);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->amount;
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
            $tax_amount->amount = round($this->invoice->amount * (1 / $this->invoice->tax_rate1), 2);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->amount;
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
            $lea->amount = $item->line_total;
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            // $ta = new TaxAmount;
            // $ta->amount = $this->getItemTaxes($item);
            // $ta->currencyID = $this->invoice->client->currency()->Code;
            // $tt->TaxAmount = $ta;
            $item_taxes = $this->getItemTaxes($item);

            if(count($item_taxes) > 0) {
                $line->TaxTotal = $item_taxes;
            }

            $price = new Price();
            $pa = new PriceAmount();
            $pa->currencyID = $this->invoice->client->currency()->code;
            $pa->amount = $this->costWithDiscount($item);
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

    private function getItemTaxes(object $item): array
    {
        $item_taxes = [];

        if(strlen($item->tax_name1 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = round(($item->line_total * (1 / $item->tax_rate1)), 2);
            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $item->line_total;
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
            $tax_amount->amount = round(($item->line_total * (1 / $item->tax_rate2)), 2);

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
            $tax_amount->amount = round(($item->line_total * (1 / $item->tax_rate3)), 2);

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
        $contact->ElectronicMail = $this->invoice->company->owner()->email ?? 'owner@gmail.com';

        $party->Contact = $contact;

        $asp->Party = $party;

        return $asp;
    }

    private function getAccountingCustomerParty(): AccountingCustomerParty
    {

        $acp = new AccountingCustomerParty();

        $party = new Party();

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

}
