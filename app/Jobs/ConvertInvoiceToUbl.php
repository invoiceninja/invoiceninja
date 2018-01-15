<?php

namespace App\Jobs;

use App\Jobs\Job;
use CleverIt\UBL\Invoice\Generator;
use CleverIt\UBL\Invoice\Invoice;
use CleverIt\UBL\Invoice\Party;
use CleverIt\UBL\Invoice\Address;
use CleverIt\UBL\Invoice\Country;
use CleverIt\UBL\Invoice\Contact;
use CleverIt\UBL\Invoice\TaxTotal;
use CleverIt\UBL\Invoice\TaxSubTotal;
use CleverIt\UBL\Invoice\TaxCategory;
use CleverIt\UBL\Invoice\TaxScheme;
use CleverIt\UBL\Invoice\InvoiceLine;
use CleverIt\UBL\Invoice\Item;
use CleverIt\UBL\Invoice\LegalMonetaryTotal;

class ConvertInvoiceToUbl extends Job
{
    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function handle()
    {
        $invoice = $this->invoice;
        $account = $invoice->account;
        $client = $invoice->client;
        $ublInvoice = new Invoice();

        // invoice
        $ublInvoice->setId($invoice->invoice_number);
        $ublInvoice->setIssueDate(date_create($invoice->invoice_date));
        $ublInvoice->setInvoiceTypeCode('SalesInvoice');

        $supplierParty = $this->createParty($account, $invoice->user);
        $ublInvoice->setAccountingSupplierParty($supplierParty);

        $customerParty = $this->createParty($client, $client->contacts[0]);
        $ublInvoice->setAccountingCustomerParty($customerParty);

        // line items
        $invoiceLine = [];
        $taxable = $invoice->getTaxable();

        foreach ($invoice->invoice_items as $index => $item) {
            $itemTaxable = $invoice->getItemTaxable($item, $taxable);
            $item->setRelation('invoice', $invoice);
            $invoiceLines[] = $this->createInvoiceLine($invoice, $index, $item, $itemTaxable);
        }

        $ublInvoice->setInvoiceLines($invoiceLines);

        if ($invoice->hasTaxes()) {
            $taxtotal = new TaxTotal();
            $taxAmount1 = $taxAmount2 = 0;

            if ($invoice->tax_name1 || floatval($invoice->tax_rate1)) {
                $taxAmount1 = $invoice->taxAmount($taxable, $invoice->tax_rate1);
                $taxScheme = ((new TaxScheme()))
                    ->setId($invoice->tax_name1);
                $taxtotal->addTaxSubTotal((new TaxSubTotal())
                        ->setTaxAmount($taxAmount1)
                        ->setTaxableAmount($taxable)
                        ->setTaxCategory((new TaxCategory())
                            ->setId($invoice->tax_name1)
                            ->setName($invoice->tax_name1)
                            ->setTaxScheme($taxScheme)
                            ->setPercent($invoice->tax_rate1)));
            }

            if ($invoice->tax_name2 || floatval($invoice->tax_rate2)) {
                $itemTaxAmount2 = $invoice->taxAmount($taxable, $invoice->tax_rate2);
                $taxScheme = ((new TaxScheme()))
                    ->setId($invoice->tax_name2);
                $taxtotal->addTaxSubTotal((new TaxSubTotal())
                        ->setTaxAmount($taxAmount2)
                        ->setTaxableAmount($taxable)
                        ->setTaxCategory((new TaxCategory())
                            ->setId($invoice->tax_name2)
                            ->setName($invoice->tax_name2)
                            ->setTaxScheme($taxScheme)
                            ->setPercent($invoice->tax_rate2)));
            }

            $taxtotal->setTaxAmount($taxAmount1 + $taxAmount2);
            $ublInvoice->setTaxTotal($taxtotal);
        }

        $ublInvoice->setLegalMonetaryTotal((new LegalMonetaryTotal())
            //->setLineExtensionAmount()
            ->setTaxExclusiveAmount($taxable)
            ->setPayableAmount($invoice->balance));

        return Generator::invoice($ublInvoice, $invoice->client->getCurrencyCode());
    }

    public function createParty($company, $user)
    {
        $party = new Party();
        $party->setName($company->name);
        $address = (new Address())
            ->setCityName($company->city)
            ->setStreetName($company->address1)
            ->setBuildingNumber($company->address2)
            ->setPostalZone($company->postal_code);

        if ($company->country_id) {
            $country = new Country();
            $country->setIdentificationCode($company->country->iso_3166_2);
            $address->setCountry($country);
        }

        $party->setPostalAddress($address);
        $party->setPhysicalLocation($address);

        $contact = new Contact();
        $contact->setElectronicMail($user->email);
        $party->setContact($contact);

        return $party;
    }

    public function createInvoiceLine($invoice, $index, $item, $taxable)
    {
        $invoiceLine = (new InvoiceLine())
            ->setId($index + 1)
            ->setInvoicedQuantity($item->qty)
            ->setLineExtensionAmount($item->costWithDiscount())
            ->setItem((new Item())
                ->setName($item->product_key)
                ->setDescription($item->description));
                //->setSellersItemIdentification("1ABCD"));

        if ($item->hasTaxes()) {
            $taxtotal = new TaxTotal();
            $itemTaxAmount1 = $itemTaxAmount2 = 0;

            if ($item->tax_name1 || floatval($item->tax_rate1)) {
                $itemTaxAmount1 = $invoice->taxAmount($taxable, $item->tax_rate1);
                $taxScheme = ((new TaxScheme()))
                    ->setId($item->tax_name1);
                $taxtotal->addTaxSubTotal((new TaxSubTotal())
                        ->setTaxAmount($itemTaxAmount1)
                        ->setTaxableAmount($taxable)
                        ->setTaxCategory((new TaxCategory())
                            ->setId($item->tax_name1)
                            ->setName($item->tax_name1)
                            ->setTaxScheme($taxScheme)
                            ->setPercent($item->tax_rate1)));
            }

            if ($item->tax_name2 || floatval($item->tax_rate2)) {
                $itemTaxAmount2 = $invoice->taxAmount($taxable, $item->tax_rate2);
                $taxScheme = ((new TaxScheme()))
                    ->setId($item->tax_name2);
                $taxtotal->addTaxSubTotal((new TaxSubTotal())
                        ->setTaxAmount($itemTaxAmount2)
                        ->setTaxableAmount($taxable)
                        ->setTaxCategory((new TaxCategory())
                            ->setId($item->tax_name2)
                            ->setName($item->tax_name2)
                            ->setTaxScheme($taxScheme)
                            ->setPercent($item->tax_rate2)));
            }

            $taxtotal->setTaxAmount($itemTaxAmount1 + $itemTaxAmount2);
            $invoiceLine->setTaxTotal($taxtotal);
        }

        return $invoiceLine;
    }
}
