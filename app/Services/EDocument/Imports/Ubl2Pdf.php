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
        $invoiceLines = $this->invoiceLines();
        $totals = $this->totals();
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
            'name' => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyName.0.name', ''),
            'address1' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.StreetName', ''),
            'address2' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.AdditionalStreetName', ''),
            'city' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CityName', ''),
            'state' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CountrySubentity', ''),
            'postal_code' => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.PostalZone', ''),
            'country_id' => $this->resolveCountry(data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.Country.IdentificationCode.value', '')),
            'vat_number' => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyTaxScheme.0.CompanyID.value', ''),
            'contacts' => [
                'first_name' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Name', ''),
                'phone' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Telephone', ''),
                'email' => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.ElectronicMail', ''),
            ],
        ];
    }

    private function invoiceLines(): array
    {
        $lines = data_get($this->invoice, 'invoiceLine', []);
        return array_map(function ($line) {
            return [
                'quantity' => data_get($line, 'InvoicedQuantity',0),
                'unit_code' => data_get($line, 'InvoicedQuantity.UnitCode',0),
                'line_extension_amount' => data_get($line, 'LineExtensionAmount',0),
                'item' => [
                    'name' => data_get($line, 'Item.Name',''),
                    'description' => data_get($line, 'Item.Description',''),
                ],
                'price' => data_get($line, 'Price.PriceAmount',0),
            ];
        }, $lines);
    }

    private function totals(): array
    {
        return [
            'line_extension_amount' => data_get($this->invoice, 'LegalMonetaryTotal.LineExtensionAmount',0),
            'tax_exclusive_amount' => data_get($this->invoice, 'LegalMonetaryTotal.TaxExclusiveAmount',0),
            'tax_inclusive_amount' => data_get($this->invoice, 'LegalMonetaryTotal.TaxInclusiveAmount',0),
            'charge_total_amount' => data_get($this->invoice, 'LegalMonetaryTotal.ChargeTotalAmount',0),
            'payable_amount' => data_get($this->invoice, 'LegalMonetaryTotal.PayableAmount',0),
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