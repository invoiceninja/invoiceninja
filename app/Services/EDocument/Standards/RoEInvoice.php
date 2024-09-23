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
use CleverIt\UBL\Invoice\Address;
use CleverIt\UBL\Invoice\ClassifiedTaxCategory;
use CleverIt\UBL\Invoice\Contact;
use CleverIt\UBL\Invoice\Country;
use CleverIt\UBL\Invoice\Generator;
use CleverIt\UBL\Invoice\Invoice as UBLInvoice;
use CleverIt\UBL\Invoice\InvoiceLine;
use CleverIt\UBL\Invoice\Item;
use CleverIt\UBL\Invoice\LegalEntity;
use CleverIt\UBL\Invoice\LegalMonetaryTotal;
use CleverIt\UBL\Invoice\Party;
use CleverIt\UBL\Invoice\PayeeFinancialAccount;
use CleverIt\UBL\Invoice\PaymentMeans;
use CleverIt\UBL\Invoice\Price;
use CleverIt\UBL\Invoice\TaxCategory;
use CleverIt\UBL\Invoice\TaxScheme;
use CleverIt\UBL\Invoice\TaxSubTotal;
use CleverIt\UBL\Invoice\TaxTotal;
use App\Models\Product;

/**
 * Requirements:
 *  FACT1:
 *  Bank ID =>   company->settings->custom_value1
 *  Bank Name => company->settings->custom_value2
 *  Sector Code => company->settings->state
 *  Sub Entity Code => company->settings->city
 *  Payment Means => invoice.custom_value1
 */
class RoEInvoice extends AbstractService
{
    private array $countrySubEntity = [
        'RO-AB' => 'Alba',
        'RO-AG' => 'Argeș',
        'RO-AR' => 'Arad',
        'RO-B' => 'Bucharest',
        'RO-BC' => 'Bacău',
        'RO-BH' => 'Bihor',
        'RO-BN' => 'Bistrița-Năsăud',
        'RO-BR' => 'Brăila',
        'RO-BT' => 'Botoșani',
        'RO-BV' => 'Brașov',
        'RO-BZ' => 'Buzău',
        'RO-CJ' => 'Cluj',
        'RO-CL' => 'Călărași',
        'RO-CS' => 'Caraș-Severin',
        'RO-CT' => 'Constanța',
        'RO-CV' => 'Covasna',
        'RO-DB' => 'Dâmbovița',
        'RO-DJ' => 'Dolj',
        'RO-GJ' => 'Gorj',
        'RO-GL' => 'Galați',
        'RO-GR' => 'Giurgiu',
        'RO-HD' => 'Hunedoara',
        'RO-HR' => 'Harghita',
        'RO-IF' => 'Ilfov',
        'RO-IL' => 'Ialomița',
        'RO-IS' => 'Iași',
        'RO-MH' => 'Mehedinți',
        'RO-MM' => 'Maramureș',
        'RO-MS' => 'Mureș',
        'RO-NT' => 'Neamț',
        'RO-OT' => 'Olt',
        'RO-PH' => 'Prahova',
        'RO-SB' => 'Sibiu',
        'RO-SJ' => 'Sălaj',
        'RO-SM' => 'Satu Mare',
        'RO-SV' => 'Suceava',
        'RO-TL' => 'Tulcea',
        'RO-TM' => 'Timiș',
        'RO-TR' => 'Teleorman',
        'RO-VL' => 'Vâlcea',
        'RO-VN' => 'Vaslui',
        'RO-VS' => 'Vrancea',
    ];

    private array $sectorList = [
        'SECTOR1' => 'Agriculture',
        'SECTOR2' => 'Manufacturing',
        'SECTOR3' => 'Tourism',
        'SECTOR4' => 'Information Technology (IT):',
        'SECTOR5' => 'Energy',
        'SECTOR6' => 'Healthcare',
        'SECTOR7' => 'Education',
    ];

    private array $sectorCodes = [
        'RO-AB'  => 'Manufacturing, Agriculture',
        'RO-AG'  => 'Manufacturing, Agriculture',
        'RO-AR'  => 'Manufacturing, Agriculture',
        'RO-B'  => 'Information Technology (IT), Education, Tourism',
        'RO-BC'  => 'Manufacturing, Agriculture',
        'RO-BH'  => 'Agriculture, Manufacturing',
        'RO-BN'  => 'Agriculture',
        'RO-BR'  => 'Agriculture',
        'RO-BT'  => 'Agriculture',
        'RO-BV'  => 'Tourism, Agriculture',
        'RO-BZ'  => 'Agriculture',
        'RO-CJ'  => 'Information Technology (IT), Education, Tourism',
        'RO-CL'  => 'Agriculture',
        'RO-CS'  => 'Manufacturing, Agriculture',
        'RO-CT'  => 'Tourism, Agriculture',
        'RO-CV'  => 'Agriculture',
        'RO-DB'  => 'Agriculture',
        'RO-DJ'  => 'Agriculture',
        'RO-GJ'  => 'Manufacturing, Agriculture',
        'RO-GL'  => 'Energy, Manufacturing',
        'RO-GR'  => 'Agriculture',
        'RO-HD'  => 'Energy, Manufacturing',
        'RO-HR'  => 'Agriculture',
        'RO-IF'  => 'Information Technology (IT), Education',
        'RO-IL'  => 'Agriculture',
        'RO-IS'  => 'Information Technology (IT), Education, Agriculture',
        'RO-MH'  => 'Manufacturing, Agriculture',
        'RO-MM'  => 'Agriculture',
        'RO-MS'  => 'Energy, Manufacturing, Agriculture',
        'RO-NT'  => 'Agriculture',
        'RO-OT'  => 'Agriculture',
        'RO-PH'  => 'Energy, Manufacturing',
        'RO-SB'  => 'Manufacturing, Agriculture',
        'RO-SJ'  => 'Agriculture',
        'RO-SM'  => 'Agriculture',
        'RO-SV'  => 'Agriculture',
        'RO-TL'  => 'Agriculture',
        'RO-TM'  => 'Agriculture, Manufacturing',
        'RO-TR'  => 'Agriculture',
        'RO-VL'  => 'Agriculture',
        'RO-VN'  => 'Agriculture',
        'RO-VS'  => 'Agriculture',
    ];

    public function __construct(public Invoice $invoice)
    {
    }

    private function resolveSubEntityCode(string $city)
    {
        $city_references = &$this->countrySubEntity[$city];

        return $city_references ?? 'RO-B';
    }

    private function resolveSectorCode(string $state)
    {
        return in_array($state, $this->sectorList) ? $state : 'SECTOR1';
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

        $ubl_invoice->setCustomizationID("urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1");
        // invoice
        $ubl_invoice->setId($invoice->number); //@phpstan-ignore-line
        $ubl_invoice->setIssueDate(date_create($invoice->date));
        $ubl_invoice->setDueDate(date_create($invoice->due_date));
        $ubl_invoice->setInvoiceTypeCode("380");
        $ubl_invoice->setDocumentCurrencyCode($invoice->client->getCurrencyCode());
        $ubl_invoice->setTaxCurrencyCode($invoice->client->getCurrencyCode());

        $taxName = '';

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
        ->setPaymentMeansCode($invoice->custom_value1)
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
        } else {
            $taxRatePercent = 0;
        }

        if (!empty($item->tax_name1)) {
            $taxNameScheme = $item->tax_name1;
        } elseif (!empty($item->tax_name2)) {
            $taxNameScheme = $item->tax_name2;
        } elseif (!empty($item->tax_name3)) {
            $taxNameScheme = $item->tax_name3;
        } else {
            $taxNameScheme = '';
        }

        $invoicing_data = $this->invoice->calc();
        $taxtotal = new TaxTotal();
        $taxtotal->setTaxAmount($invoicing_data->getItemTotalTaxes());
        $taxtotal->addTaxSubTotal((new TaxSubTotal())
            ->setTaxAmount($invoicing_data->getItemTotalTaxes())
            ->setTaxableAmount($taxable)
            ->setTaxCategory((new TaxCategory())
                ->setId("S")
                ->setPercent($taxRatePercent)
                ->setTaxScheme(((new TaxScheme())->setId(($taxNameScheme === 'TVA') ? 'VAT' : $taxNameScheme)))));

        $ubl_invoice->setTaxTotal($taxtotal);

        $ubl_invoice->setLegalMonetaryTotal((new LegalMonetaryTotal())
            ->setLineExtensionAmount($taxable)
            ->setTaxExclusiveAmount($taxable)
            ->setTaxInclusiveAmount($invoice->amount)
            ->setPayableAmount($invoice->amount));

        return $ubl_invoice;
    }

    private function createParty($company, $vatNr, $eMail, $phone, $idNr, $fullName, $compType, $taxNameScheme = '')
    {
        $party = new Party();
        $party->setPartyIdentification(preg_replace('/^RO/', '', $vatNr));
        $address = new Address();
        if ($compType === 'company') {
            $address->setCityName($this->resolveSectorCode($company->settings->state));
            $address->setStreetName($company->settings->address1);
            $address->setCountrySubentity($this->resolveSubEntityCode($company->settings->city));
        } elseif ($compType === 'client') {
            $address->setCityName($this->resolveSectorCode($company->state));
            $address->setStreetName($company->address1);
            $address->setCountrySubentity($this->resolveSubEntityCode($company->city));
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
            ->setId($this->resolveTaxCode($item->tax_id ?? 1))
            ->setPercent($item->tax_rate1)
            ->setTaxScheme(((new TaxScheme())->setId(($item->tax_name1 === 'TVA') ? 'VAT' : $item->tax_name1)));
        } elseif (strlen($item->tax_name2) > 1) {
            $classifiedTaxCategory = (new ClassifiedTaxCategory())
            ->setId($this->resolveTaxCode($item->tax_id ?? 1))
            ->setPercent($item->tax_rate2)
            ->setTaxScheme(((new TaxScheme())->setId(($item->tax_name2 === 'TVA') ? 'VAT' : $item->tax_name2)));
        } elseif (strlen($item->tax_name3) > 1) {
            $classifiedTaxCategory = (new ClassifiedTaxCategory())
            ->setId($this->resolveTaxCode($item->tax_id ?? 1))
            ->setPercent($item->tax_rate3)
            ->setTaxScheme(((new TaxScheme())->setId(($item->tax_name3 === 'TVA') ? 'VAT' : $item->tax_name3)));
        } else {

            $classifiedTaxCategory = (new ClassifiedTaxCategory())
            ->setId($this->resolveTaxCode($item->tax_id ?? 8))
            ->setPercent(0)
            ->setTaxScheme(((new TaxScheme())->setId(($item->tax_name3 === 'TVA') ? 'VAT' : $item->tax_name3)));

        }

        $invoiceLine = (new InvoiceLine())
            ->setId($index + 1)
            ->setInvoicedQuantity($item->quantity)
            ->setUnitCode($item->unit_code ?? 'C62')
            ->setLineExtensionAmount($item->line_total)
            ->setItem((new Item())
                ->setName($item->product_key)
                ->setDescription($item->notes)
                ->setClassifiedTaxCategory([$classifiedTaxCategory]))
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
     * @return float
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

    private function resolveTaxCode($tax_id)
    {
        $code = $tax_id;

        match($tax_id) {
            Product::PRODUCT_TYPE_REVERSE_TAX => $code = 'AE', // VAT_REVERSE_CHARGE =
            Product::PRODUCT_TYPE_EXEMPT => $code = 'E', // EXEMPT_FROM_TAX =
            Product::PRODUCT_TYPE_PHYSICAL => $code = 'S', // STANDARD_RATE =
            Product::PRODUCT_TYPE_ZERO_RATED => $code = 'Z', // ZERO_RATED_GOODS =
            Product::PRODUCT_TYPE_REDUCED_TAX => $code = 'AA', // LOWER_RATE =
            Product::PRODUCT_TYPE_SERVICE => $code = 'S', // STANDARD_RATE =
            Product::PRODUCT_TYPE_DIGITAL => $code = 'S', // STANDARD_RATE =
            Product::PRODUCT_TYPE_SHIPPING => $code = 'S', // STANDARD_RATE =
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $code = 'S', // STANDARD_RATE =
            default => $code = 'S',
        };

        return $code;
    }

    public function generateXml(): string
    {
        $ubl_invoice = $this->run(); // Call the existing handle method to get the UBLInvoice
        $generator = new Generator();
        return $generator->invoice($ubl_invoice, $this->invoice->client->getCurrencyCode());
    }

}
