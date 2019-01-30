<?php

namespace App\Jobs;

use Utils;
use Exception;
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
    const INVOICE_TYPE_STANDARD = 380;
    const INVOICE_TYPE_CREDIT = 381;

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
        $ublInvoice->setInvoiceTypeCode($invoice->amount < 0 ? self::INVOICE_TYPE_CREDIT : self::INVOICE_TYPE_STANDARD);

        $supplierParty = $this->createParty($account, $invoice->user);
        $ublInvoice->setAccountingSupplierParty($supplierParty);

        $customerParty = $this->createParty($client, $client->contacts[0]);
        $ublInvoice->setAccountingCustomerParty($customerParty);

        // line items
        $invoiceLines = [];
        $taxable = $invoice->getTaxable();

        foreach ($invoice->invoice_items as $index => $item) {
            $itemTaxable = $invoice->getItemTaxable($item, $taxable);
            $item->setRelation('invoice', $invoice);
            $invoiceLines[] = $this->createInvoiceLine($index, $item, $itemTaxable);
        }

        $ublInvoice->setInvoiceLines($invoiceLines);

        $taxtotal = new TaxTotal();
        $taxAmount1 = $taxAmount2 = 0;

        $taxAmount1 = $this->createTaxRate($taxtotal, $taxable, $invoice->tax_rate1, $invoice->tax_name1);
        if ($invoice->tax_name2 || floatval($invoice->tax_rate2)) {
            $taxAmount2 = $this->createTaxRate($taxtotal, $taxable, $invoice->tax_rate2, $invoice->tax_name2);
        }

        $taxtotal->setTaxAmount($taxAmount1 + $taxAmount2);
        $ublInvoice->setTaxTotal($taxtotal);

        $ublInvoice->setLegalMonetaryTotal((new LegalMonetaryTotal())
            //->setLineExtensionAmount()
            ->setTaxExclusiveAmount($taxable)
            ->setPayableAmount($invoice->balance));

        try {
            return Generator::invoice($ublInvoice, $invoice->client->getCurrencyCode());
        } catch (Exception $exception) {
            Utils::logError($exception);

            return false;
        }
    }

    private function createParty($company, $user)
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

    private function createInvoiceLine($index, $item, $taxable)
    {
        $invoiceLine = (new InvoiceLine())
            ->setId($index + 1)
            ->setInvoicedQuantity($item->qty)
            ->setLineExtensionAmount($item->costWithDiscount())
            ->setItem((new Item())
                ->setName($item->product_key)
                ->setDescription($item->description));
                //->setSellersItemIdentification("1ABCD"));

        $taxtotal = new TaxTotal();
        $itemTaxAmount1 = $itemTaxAmount2 = 0;

        $itemTaxAmount1 = $this->createTaxRate($taxtotal, $taxable, $item->tax_rate1, $item->tax_name1);
        if ($item->tax_name2 || floatval($item->tax_rate2)) {
            $itemTaxAmount2 = $this->createTaxRate($taxtotal, $taxable, $item->tax_rate2, $item->tax_name2);
        }

        $taxtotal->setTaxAmount($itemTaxAmount1 + $itemTaxAmount2);
        $invoiceLine->setTaxTotal($taxtotal);

        return $invoiceLine;
    }

    private function createTaxRate(&$taxtotal, $taxable, $taxRate, $taxName)
    {
        $invoice = $this->invoice;
        $taxAmount = $invoice->taxAmount($taxable, $taxRate);
        $taxScheme = ((new TaxScheme()))->setId($taxName);

        $taxtotal->addTaxSubTotal((new TaxSubTotal())
                ->setTaxAmount($taxAmount)
                ->setTaxableAmount($taxable)
                ->setTaxCategory((new TaxCategory())
                    ->setId($taxName)
                    ->setName($taxName)
                    ->setTaxScheme($taxScheme)
                    ->setPercent($taxRate)));

        return $taxAmount;
    }
}
