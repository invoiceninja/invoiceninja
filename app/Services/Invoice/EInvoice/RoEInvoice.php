<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice\EInvoice;

use App\Models\Invoice;
use App\Services\AbstractService;
use CleverIt\UBL\Invoice\Address;
use CleverIt\UBL\Invoice\Contact;
use CleverIt\UBL\Invoice\Country;
use CleverIt\UBL\Invoice\Generator;
use CleverIt\UBL\Invoice\Invoice as UBLInvoice;
use CleverIt\UBL\Invoice\InvoiceLine;
use CleverIt\UBL\Invoice\Item;
use CleverIt\UBL\Invoice\LegalMonetaryTotal;
use CleverIt\UBL\Invoice\Party;
use CleverIt\UBL\Invoice\TaxCategory;
use CleverIt\UBL\Invoice\TaxScheme;
use CleverIt\UBL\Invoice\TaxSubTotal;
use CleverIt\UBL\Invoice\TaxTotal;
use CleverIt\UBL\Invoice\PaymentMeans;
use CleverIt\UBL\Invoice\PayeeFinancialAccount;
use CleverIt\UBL\Invoice\LegalEntity;
use CleverIt\UBL\Invoice\ClassifiedTaxCategory;
use CleverIt\UBL\Invoice\Price;

class RoEInvoice extends AbstractService
{
    public function __construct(public Invoice $invoice)
    {
    }

    /**
     * Execute the job
     * @return UBLInvoice
     */
    public function run(): UBLInvoice
    {
        $invoice = $this->invoice;
        $company = $invoice->company;
        $client = $invoice->client;
        $companyVatNr = $company->settings->vat_number;
        $clientVatNr = $client->vat_number;
        $companyIdn = $company->settings->id_number;
        $clientIdn = $client->id_number;
        $coUserFirstName = $company->owner()->present()->firstName();
        $coUserLastName = $company->owner()->present()->lastName();
        $coFullName = $coUserFirstName . ' ' . $coUserLastName;
        $clUserFirstName = $client->present()->first_name();
        $clUserLastName = $client->present()->last_name();
        $clFullName = $clUserFirstName . ' ' . $clUserLastName;
        $coEmail = $company->settings->email;
        $coPhone = $company->settings->phone;
        $clPhone = $client->present()->phone();
        $clEmail = $client->present()->email();

        $ubl_invoice = new UBLInvoice();

        // invoice
        $ubl_invoice->setId($invoice->custom_value1 . ' ' . $invoice->number);
        $ubl_invoice->setIssueDate(date_create($invoice->date));
        $ubl_invoice->setDueDate(date_create($invoice->due_date));
        $ubl_invoice->setInvoiceTypeCode(explode('-', $invoice->custom_value3)[0]);
        $ubl_invoice->setDocumentCurrencyCode($invoice->client->getCurrencyCode());
        $ubl_invoice->setTaxCurrencyCode($invoice->client->getCurrencyCode());

        foreach ($invoice->line_items as $index => $item) {

            if (!empty($item->tax_name1)) {
                $taxName = $item->tax_name1;
            } elseif (!empty($item->tax_name2)) {
                $taxName = $item->tax_name2;
            } elseif (!empty($item->tax_name3)) {
                $taxName = $item->tax_name3;
            }
        }

        $supplier_party = $this->createParty($company, $companyVatNr, $coEmail, $coPhone, $companyIdn, $coFullName, 'company', $taxName);
        $ubl_invoice->setAccountingSupplierParty($supplier_party);

        $customer_party = $this->createParty($client, $clientVatNr, $clEmail, $clPhone, $clientIdn, $clFullName, 'client', $taxName);
        $ubl_invoice->setAccountingCustomerParty($customer_party);

        $payeeFinancialAccount = (new PayeeFinancialAccount())
            ->setBankId($company->settings->custom_value1)
            ->setBankName($company->settings->custom_value2);
        $paymentMeans = (new PaymentMeans())
            ->setPaymentMeansCode($invoice->custom_value2)
            ->setPayeeFinancialAccount($payeeFinancialAccount);
        $ubl_invoice->setPaymentMeans($paymentMeans);

        // line items
        $invoice_lines = [];
        $taxable = $this->getTaxable();

        foreach ($invoice->line_items as $index => $item) {
            $invoice_lines[] = $this->createInvoiceLine($index, $item);
        }

        $ubl_invoice->setInvoiceLines($invoice_lines);

        if (!empty($item->tax_rate1)) {
            $taxRatePercent = $item->tax_rate1;
        } elseif (!empty($item->tax_rate2)) {
            $taxRatePercent = $item->tax_rate2;
        } elseif (!empty($item->tax_rate3)) {
            $taxRatePercent = $item->tax_rate3;
        }

        if (!empty($item->tax_name1)) {
            $taxNameScheme = $item->tax_name1;
        } elseif (!empty($item->tax_name2)) {
            $taxNameScheme = $item->tax_name2;
        } elseif (!empty($item->tax_name3)) {
            $taxNameScheme = $item->tax_name3;
        }

        $invoicing_data = $this->invoice->calc();
        $taxtotal = new TaxTotal();
        $taxtotal->setTaxAmount($invoicing_data->getItemTotalTaxes());
        $taxtotal->addTaxSubTotal((new TaxSubTotal())
            ->setTaxAmount($invoicing_data->getItemTotalTaxes())
            ->setTaxableAmount($taxable)
            ->setTaxCategory((new TaxCategory())
                ->setId(explode('-', $company->settings->custom_value3)[0])
                ->setPercent($taxRatePercent)
                ->setTaxScheme(($taxNameScheme === 'TVA') ? 'VAT' : $taxNameScheme)));
        $ubl_invoice->setTaxTotal($taxtotal);

        $ubl_invoice->setLegalMonetaryTotal((new LegalMonetaryTotal())
            ->setLineExtensionAmount($taxable)
            ->setTaxExclusiveAmount($taxable)
            ->setTaxInclusiveAmount($invoice->amount)
            ->setPayableAmount($invoice->amount));

        return $ubl_invoice;
    }

    private function createParty($company, $vatNr, $eMail, $phone, $idNr, $fullName, $compType, $taxNameScheme)
    {
        $party = new Party();
        $party->setPartyIdentification(preg_replace('/^RO/', '', $vatNr));
        $address = new Address();
        if ($compType === 'company') {
            $address->setCityName($company->settings->state);
            $address->setStreetName($company->settings->address1);
            $address->setCountrySubentity($company->settings->city);
        } elseif ($compType === 'client') {
            $address->setCityName($company->state);
            $address->setStreetName($company->address1);
            $address->setCountrySubentity($company->city);
        }

        if ($compType === 'company') {
            if ($company->settings->country_id) {
                $country = new Country();
                $country->setIdentificationCode($company->country()->iso_3166_2);
                $address->setCountry($country);
            }
        } elseif ($compType === 'client') {
            if ($company->country_id) {
                $country = new Country();
                $country->setIdentificationCode($company->country->iso_3166_2);
                $address->setCountry($country);
            }
        }

        $party->setPostalAddress($address);

        $taxScheme = null;
        if (preg_match('/^RO/', $vatNr)) {
            $taxScheme = (new TaxScheme())
                ->setCompanyId($vatNr)
                ->setId(($taxNameScheme === 'TVA') ? 'VAT' : $taxNameScheme);
        }

        $party->setTaxScheme($taxScheme);

        $legalEntity = new LegalEntity();
        if ($compType === 'company') {
            $legalEntity->setRegistrationName($company->settings->name);
        } elseif ($compType === 'client') {
            $legalEntity->setRegistrationName($company->name);
        }

        if (preg_match('/^RO/', $vatNr)) {
            $legalEntity->setCompanyId($idNr);
        } else {
            $legalEntity->setCompanyId($vatNr);
        }

        $party->setLegalEntity($legalEntity);

        $contact = (new Contact())
            ->setName($fullName)
            ->setElectronicMail($eMail)
            ->setTelephone($phone);
        $party->setContact($contact);

        return $party;
    }

    private function createInvoiceLine($index, $item)
    {
        if (strlen($item->tax_name1) > 1) {
            $classifiedTaxCategory = (new ClassifiedTaxCategory())
            ->setId(explode('-', $item->custom_value4)[0])
            ->setPercent($item->tax_rate1)
            ->setTaxScheme(($item->tax_name1 === 'TVA') ? 'VAT' : $item->tax_name1);
        } elseif (strlen($item->tax_name2) > 1) {
            $classifiedTaxCategory = (new ClassifiedTaxCategory())
            ->setId(explode('-', $item->custom_value4)[0])
            ->setPercent($item->tax_rate2)
            ->setTaxScheme(($item->tax_name2 === 'TVA') ? 'VAT' : $item->tax_name2);
        } elseif (strlen($item->tax_name3) > 1) {
            $classifiedTaxCategory = (new ClassifiedTaxCategory())
            ->setId(explode('-', $item->custom_value4)[0])
            ->setPercent($item->tax_rate3)
            ->setTaxScheme(($item->tax_name3 === 'TVA') ? 'VAT' : $item->tax_name3);
        }
        $invoiceLine = (new InvoiceLine())
            ->setId($index + 1)
            ->setInvoicedQuantity($item->quantity)
            ->setUnitCode($item->custom_value3)
            ->setLineExtensionAmount($item->line_total)
            ->setItem((new Item())
                ->setName($item->product_key)
                ->setDescription($item->notes)
                ->setClassifiedTaxCategory($classifiedTaxCategory))
            ->setPrice((new Price())
                ->setPriceAmount($this->costWithDiscount($item)));

        //->setSellersItemIdentification("1ABCD"));

        return $invoiceLine;
    }

    private function createTaxRate(&$taxtotal, $taxable, $taxRate, $taxName)
    {
        $invoice = $this->invoice;
        $taxAmount = $this->taxAmount($taxable, $taxRate);
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

    /**
     * @param $item
     * @param $invoice_total
     * @return float|int
     */
    private function getItemTaxable($item, $invoice_total)
    {
        $total = $item->quantity * $item->cost;

        if ($this->invoice->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                /** @var float $invoice_total */
                if ($invoice_total + $this->invoice->discount != 0) {
                    $total -= $invoice_total ? ($total / ($invoice_total + $this->invoice->discount) * $this->invoice->discount) : 0;
                }
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
            }
        }

        if ($item->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $item->discount;
            } else {
                $total -= $total * $item->discount / 100;
            }
        }

        return round($total, 2);
    }

    /**
     * @return float|int|mixed
     */
    private function getTaxable()
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

    public function costWithDiscount($item)
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

    public function taxAmount($taxable, $rate)
    {
        if ($this->invoice->uses_inclusive_taxes) {
            return round($taxable - ($taxable / (1 + ($rate / 100))), 2);
        } else {
            return round($taxable * ($rate / 100), 2);
        }
    }

    public function generateXml(): string
    {
        $ubl_invoice = $this->run(); // Call the existing handle method to get the UBLInvoice
        $generator = new Generator();
        return $generator->invoice($ubl_invoice, $this->invoice->client->getCurrencyCode());
    }

}
