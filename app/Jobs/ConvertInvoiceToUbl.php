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
        $ublInvoice->setIssueDate($invoice->invoice_date);
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
            $customerAddress->setCountry($client);
        }

        $customerParty->setPostalAddress($customerAddress);
        $customerParty->setPhysicalLocation($customerAddress);

        $contact = new Contact();
        $contact->setElectronicMail($client->contacts[0]->email);
        $customerParty->setContact($contact);

        $ublInvoice->setAccountingCustomerParty($customerParty);

        $taxtotal = (new \CleverIt\UBL\Invoice\TaxTotal())
            ->setTaxAmount(10)
            ->setTaxSubTotal((new \CleverIt\UBL\Invoice\TaxSubTotal())
                ->setTaxAmount(10)
                ->setTaxableAmount(100)
                ->setTaxCategory((new \CleverIt\UBL\Invoice\TaxCategory())
                    ->setId("H")
                    ->setName("NL, Hoog Tarief")
                    ->setPercent(21.00)));

        $invoiceLine = (new \CleverIt\UBL\Invoice\InvoiceLine())
            ->setId(1)
            ->setInvoicedQuantity(1)
            ->setLineExtensionAmount(100)
            ->setTaxTotal($taxtotal)
            ->setItem((new \CleverIt\UBL\Invoice\Item())->setName("Test item")->setDescription("test item description")->setSellersItemIdentification("1ABCD"));

        $ublInvoice->setInvoiceLines([$invoiceLine]);
        $ublInvoice->setTaxTotal($taxtotal);

        $ublInvoice->setLegalMonetaryTotal((new \CleverIt\UBL\Invoice\LegalMonetaryTotal())
            ->setLineExtensionAmount(100)
            ->setTaxExclusiveAmount(100)
            ->setPayableAmount(-1000)
            ->setAllowanceTotalAmount(50));

        return $xmlService->write('Invoice', [
            $ublInvoice
        ]);
    }
}
