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

namespace App\Services\EDocument\Imports;

use App\Models\Vendor;
use App\Models\Company;
use App\Models\Country;
use App\Models\Expense;
use App\Factory\VendorFactory;
use App\Factory\ExpenseFactory;
use App\Services\AbstractService;
use Illuminate\Http\UploadedFile;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\SavesDocuments;
use App\Factory\VendorContactFactory;
use App\Models\Currency;
use App\Repositories\ExpenseRepository;

class Ubl2Pdf extends AbstractService
{

    /**
     * @throws \Throwable
     */
    public function __construct(public \InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice, public Company $company)
    {
    }

    public function run()
    {
        $client = $this->clientDetails();
        $supplier = $this->supplierDetails();
        $invoiceDetails = $this->invoiceDetails();
        $totals = $this->totals();

        nlog($client);
        nlog($supplier);
        nlog($invoiceDetails);
        nlog($totals);

    }

    private function clientDetails(): array
    {
        return [
            'name' => data_get($this->invoice, 'AccountingCustomerParty.Party.PartyName.0.Name',''),
            'address1' => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.StreetName',''),
            'address2' => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.AdditionalStreetName',''),
            'city' => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.CityName',''),
            'state' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CountrySubentity',''),
            'postal_code' => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.PostalZone',''),
            'country_id' => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.Country.IdentificationCode.value',''),
            'vat_number' => data_get($this->invoice, 'AccountingCustomerParty.Party.PartyTaxScheme.0.CompanyID.value',''),
            'contacts' => [
                'first_name' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Name',''),
                'phone' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Telephone',''),
                'email' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.ElectronicMail',''),
            ],
            'settings' => [
                'currency_id' => $this->resolveCurrencyId(data_get($this->invoice, 'DocumentCurrencyCode.value', $this->company->currency()->code))
            ]
        ];
    }

    private function supplierDetails(): array
    {
        return [
            'name' => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyName.0.Name', ''),
            'address1' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.StreetName', ''),
            'address2' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.AdditionalStreetName', ''),
            'city' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CityName', ''),
            'state' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CountrySubentity', ''),
            'postal_code' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.PostalZone', ''),
            'country_id' => $this->resolveCountry(data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.Country.IdentificationCode.value', '')),
            'routing_id' => data_get($this->invoice, 'AccountingSupplierParty.Party.EndpointID.value', ''),
            'id_number' => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyIdentification.0.ID.value', false),
            'vat_number' => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyTaxScheme.0.CompanyID.value', ''),
            'currency_id' => $this->resolveCurrencyId(data_get($this->invoice, 'DocumentCurrencyCode.value', $this->company->currency()->code)),
            'contacts' => [
                'first_name' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Name', ''),
                'phone' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Telephone', ''),
                'email' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.ElectronicMail', ''),
            ],
        ];
    }

    private function invoiceDetails(): array
    {
        return [
            'number' => data_get($this->invoice, 'ID.value', ''),
            'date' => data_get($this->invoice, 'IssueDate', ''),
            'due_date' => data_get($this->invoice, 'DueDate', ''),
            // 'type' => data_get($this->invoice, 'InvoiceTypeCode.value', ''),
            'line_items' => $this->invoiceLines(),
            'terms' => $this->harvestTerms(),
            'public_notes' => data_get($this->invoice, 'Note', '')
        ];
    }
    private function harvestTerms(): string
    {

        $payment_means = [];
        $payment_means[] = data_get($this->invoice, 'PaymentMeans.0.PaymentMeansCode.name', false);
        $payment_means[] = data_get($this->invoice, 'PaymentMeans.0.PaymentID.value', false);
        $payment_means[] = data_get($this->invoice, 'PaymentMeans.0.PayeeFinancialAccount.ID.value', false);
        $payment_means[] = data_get($this->invoice, 'PaymentMeans.0.PayeeFinancialAccount.Name', false);
        $payment_means[] = data_get($this->invoice, 'PaymentMeans.0.PayeeFinancialAccount.FinancialInstitutionBranch.ID.value', false);
        $payment_means[] = data_get($this->invoice, 'PaymentTerms.0.Note', false);

        $private_notes = collect($payment_means)
                                ->reject(function ($means) {
                                    return $means === false;
                                })->implode("\n");

        return $private_notes;

    }

    private function invoiceLines(): array
    {
        $lines = data_get($this->invoice, 'InvoiceLine', []);

        return array_map(function ($line) {
            return [
                'quantity' => data_get($line, 'InvoicedQuantity.amount', 0),
                'unit_code' => data_get($line, 'InvoicedQuantity.UnitCode','C62'),
                'product_key' => data_get($line, 'Item.Name', ''),
                'notes' =>  data_get($line, 'Item.Description', ''),
                'cost' => data_get($line, 'Price.PriceAmount.value', 0),
                'tax_name1' => data_get($line, 'Item.ClassifiedTaxCategory.0.TaxScheme.ID.value', ''),
                'tax_rate1' => data_get($line, 'Item.ClassifiedTaxCategory.0.Percent', 0),
                'tax_name2' => data_get($line, 'Item.ClassifiedTaxCategory.1.TaxScheme.ID.value', ''),
                'tax_rate2' => data_get($line, 'Item.ClassifiedTaxCategory.1.Percent', 0),
                'tax_name3' => data_get($line, 'Item.ClassifiedTaxCategory.2.TaxScheme.ID.value', ''),
                'tax_rate3' => data_get($line, 'Item.ClassifiedTaxCategory.2.Percent', 0),
                'line_extension_amount' => data_get($line, 'LineExtensionAmount.amount', 0),
            ];
        }, $lines);
    }

    private function totals(): array
    {
        $tax_inc = data_get($this->invoice, 'LegalMonetaryTotal.TaxInclusiveAmount.amount', 0);
        $tax_ex = data_get($this->invoice, 'LegalMonetaryTotal.TaxExclusiveAmount.amount', 0);
        $tax_amount = data_get($this->invoice, 'TaxTotal.0.TaxAmount', 0);

        $taxes = [];

        foreach(data_get($this->invoice, 'TaxTotal.0.TaxSubtotal', []) as $tax_subtotal)
        {
            $taxes[] = [
                'subtotal' => data_get($tax_subtotal, 'TaxableAmount.amount', 0),
                'tax_name' => data_get($tax_subtotal, 'TaxCategory.TaxScheme.ID.value', ''),
                'tax_rate' => data_get($tax_subtotal, 'TaxAmount.amount', 0),
            ];
        }

        return [
            'subtotal' => data_get($this->invoice, 'LegalMonetaryTotal.LineExtensionAmount.amount', 0),
            'total' => data_get($this->invoice, 'LegalMonetaryTotal.TaxInclusiveAmount.amount', 0),
            'taxes' => $taxes,
        ];
    }

    private function resolveCountry(?string $iso_country_code): int
    {
        return Country::query()
                        ->where('iso_3166_2', $iso_country_code)
                        ->orWhere('iso_3166_3', $iso_country_code)
                        ->first()?->id ?? (int)$this->company->settings->country_id;
    }


    private function resolveCurrencyId(string $currency_code): int
    {
        
        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        return $currencies->first(function (Currency $c) use ($currency_code) {
            return $c->code === $currency_code;
        })?->id ?? (int) $this->company->settings->currency_id;
    }
}