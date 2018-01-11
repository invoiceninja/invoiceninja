<?php

namespace App\Jobs;

use App\Jobs\Job;
use Sabre\Xml\Service;
use CleverIt\UBL\Invoice\Invoice;
use CleverIt\UBL\Invoice\Party;
use CleverIt\UBL\Invoice\Address;
use CleverIt\UBL\Invoice\Country;
use CleverIt\UBL\Invoice\Contact;
use CleverIt\UBL\Invoice\TaxTotal;
use CleverIt\UBL\Invoice\TaxSubTotal;
use CleverIt\UBL\Invoice\TaxCategory;
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
        $xmlService = new Service();
        $xmlService->namespaceMap = [
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2' => '',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2' => 'cbc',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2' => 'cac'
        ];

        $invoice = $this->invoice;
        $account = $invoice->account;
        $client = $invoice->client;
        $ublInvoice = new Invoice();

        // invoice
        $ublInvoice->setId($invoice->invoice_number);
        $ublInvoice->setIssueDate(date_create($invoice->invoice_date));
        $ublInvoice->setInvoiceTypeCode('SalesInvoice');

        // account
        $supplierParty = new Party();
        $supplierParty->setName($account->name);
        $supplierAddress = (new Address())
            ->setCityName($account->city)
            ->setStreetName($account->address1)
            ->setBuildingNumber($account->address2)
            ->setPostalZone($account->postal_code);

        if ($account->country_id) {
            $country = new Country();
            $country->setIdentificationCode($account->country->iso_3166_2);
            $supplierAddress->setCountry($country);
        }

        $supplierParty->setPostalAddress($supplierAddress);
        $supplierParty->setPhysicalLocation($supplierAddress);

        $contact = new Contact();
        $contact->setElectronicMail($invoice->user->email);
        $supplierParty->setContact($contact);

        $ublInvoice->setAccountingSupplierParty($supplierParty);

        // client
        $customerParty = new Party();
        $customerParty->setName($client->getDisplayName());
        $customerAddress = (new Address())
            ->setCityName($client->city)
            ->setStreetName($client->address1)
            ->setBuildingNumber($client->address2)
            ->setPostalZone($client->postal_code);

        if ($client->country_id) {
            $country = new Country();
            $country->setIdentificationCode($client->country->iso_3166_2);
            $customerAddress->setCountry($country);
        }

        $customerParty->setPostalAddress($customerAddress);
        $customerParty->setPhysicalLocation($customerAddress);

        $contact = new Contact();
        $contact->setElectronicMail($client->contacts[0]->email);
        $customerParty->setContact($contact);

        $ublInvoice->setAccountingCustomerParty($customerParty);

        // line items
        $invoiceLine = [];

        foreach ($invoice->invoice_items as $index => $item) {
            $invoiceLine = (new InvoiceLine())
                ->setId($index + 1)
                ->setInvoicedQuantity($item->qty)
                ->setLineExtensionAmount($item->cost)
                ->setItem((new Item())
                    ->setName($item->product_key)
                    ->setDescription($item->description));
                    //->setSellersItemIdentification("1ABCD"));

            if ($item->tax_name1 || $item->tax_rate1) {
                $taxtotal = (new TaxTotal())
                    ->setTaxAmount(10)
                    ->setTaxSubTotal((new TaxSubTotal())
                        ->setTaxAmount(10)
                        ->setTaxableAmount(100)
                        ->setTaxCategory((new TaxCategory())
                            ->setId("H")
                            ->setName("NL, Hoog Tarief")
                            ->setPercent(21.00)));
                $invoiceLine->setTaxTotal($taxtotal);
            }

            $invoiceLines[] = $invoiceLine;
        }

        $ublInvoice->setInvoiceLines($invoiceLines);

        if ($invoice->tax_name1 || $invoice->tax_rate1) {
            $taxtotal = (new TaxTotal())
                ->setTaxAmount(10)
                ->setTaxSubTotal((new TaxSubTotal())
                    ->setTaxAmount(10)
                    ->setTaxableAmount(100)
                    ->setTaxCategory((new TaxCategory())
                        ->setId("H")
                        ->setName("NL, Hoog Tarief")
                        ->setPercent(21.00)));
            $ublInvoice->setTaxTotal($taxtotal);
        }

        $ublInvoice->setLegalMonetaryTotal((new LegalMonetaryTotal())
            ->setLineExtensionAmount(100)
            ->setTaxExclusiveAmount(100)
            ->setPayableAmount(-1000)
            ->setAllowanceTotalAmount(50));

        return $xmlService->write('Invoice', [
            $ublInvoice
        ]);
    }
}
