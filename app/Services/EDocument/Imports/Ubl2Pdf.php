<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Imports;

use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Country;
use App\Models\Expense;
use App\Models\Currency;
use App\Factory\VendorFactory;
use App\Factory\ExpenseFactory;
use App\Services\AbstractService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\SavesDocuments;
use App\Factory\VendorContactFactory;
use App\Repositories\ExpenseRepository;
use App\Services\Template\TemplateService;

class Ubl2Pdf extends AbstractService
{
    /** @var \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote */
    public \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $invoice;

    /**
     * @throws \Throwable
     */
    public function __construct(\InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $invoice, public Company $company)
    {
        $this->invoice = $invoice;
    }

    /**
     * Renders a UBL e-invoice as a styled PDF using the TD14 HTML template.
     *
     * @return void
     */
    public function run()
    {

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->company->locale());
        $t->replace(Ninja::transformTranslations($this->company->settings));
        $template = file_get_contents(resource_path('views/templates/ubl/td14.html'));
        // nlog($client);
        // nlog($supplier);
        // nlog($invoiceDetails);
        // nlog($totals);

        $data = [
            'client' => $this->clientDetails(),
            'supplier' => $this->supplierDetails(),
            'invoiceDetails' => $this->invoiceDetails(),
            'totals' => $this->totals(),
            'metadata' => $this->metadata(),
            'translations' => $this->getGenericTranslations(),
            'css' => $this->customCss(),
        ];

        $ts = new TemplateService();

        $ts_instance = $ts->setCompany($this->company)
                    ->setData($data)
                    ->setRawTemplate($template)
                    ->parseNinjaBlocks()
                    ->save();

        nlog($ts_instance->getHtml());

    }

    /**
     * Returns a keyed array of common translated labels for the PDF template.
     *
     * @return array
     */
    private function getGenericTranslations(): array
    {
        return [
            'to' => ctrans('texts.to'),
            'from' => ctrans('texts.from'),
            'invoice' => ctrans('texts.invoice'),
            'credit' => ctrans('texts.credit'),
            'details' => ctrans('texts.details'),
            'number' => ctrans('texts.number'),
            'tax' => ctrans('texts.tax'),

            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
            // 'from' => ctrans('texts.from'),
        ];
    }

    /**
     * Strips null/empty values and formats DateTime objects using the company date format.
     *
     * @param  array $array
     * @return array
     */
    private function processValues(array $array): array
    {

        foreach ($array as $key => $value) {
            if ($value === null || $value === '') {
                unset($array[$key]);
            }

            if ($value instanceof \DateTime) {
                $array[$key] = $value->format($this->company->date_format());
            }
        }

        return $array;

    }

    /**
     * Extracts the buyer/customer party details from the Peppol invoice.
     *
     * @return array
     */
    private function clientDetails(): array
    {
        return $this->processValues([
            ctrans('texts.name') => data_get($this->invoice, 'AccountingCustomerParty.Party.PartyName.0.Name', ''),
            ctrans('texts.address1') => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.StreetName', ''),
            ctrans('texts.address2') => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.AdditionalStreetName', ''),
            ctrans('texts.city') => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.CityName', ''),
            ctrans('texts.state') => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CountrySubentity', ''),
            ctrans('texts.postal_code') => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.PostalZone', ''),
            ctrans('texts.country_id') => data_get($this->invoice, 'AccountingCustomerParty.Party.PostalAddress.Country.IdentificationCode.value', ''),
            ctrans('texts.vat_number') => data_get($this->invoice, 'AccountingCustomerParty.Party.PartyTaxScheme.0.CompanyID.value', ''),
            ctrans('texts.contact_name') => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Name', ''),
            ctrans('texts.phone') => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Telephone', ''),
            ctrans('texts.email') => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.ElectronicMail', ''),
        ]);
    }

    /**
     * Extracts the seller/supplier party details from the Peppol invoice.
     *
     * @return array
     */
    private function supplierDetails(): array
    {
        return $this->processValues([
            ctrans('texts.name') => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyName.0.Name', ''),
            ctrans('texts.address1') => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.StreetName', ''),
            ctrans('texts.address2') => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.AdditionalStreetName', ''),
            ctrans('texts.city') => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CityName', ''),
            ctrans('texts.state') => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.CountrySubentity', ''),
            ctrans('texts.postal_code') => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.PostalZone', ''),
            ctrans('texts.country_id') => data_get($this->invoice, 'AccountingSupplierParty.Party.PostalAddress.Country.IdentificationCode.value', ''),
            ctrans('texts.routing_id') => data_get($this->invoice, 'AccountingSupplierParty.Party.EndpointID.value', ''),
            ctrans('texts.id_number') => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyIdentification.0.ID.value', false),
            ctrans('texts.vat_number') => data_get($this->invoice, 'AccountingSupplierParty.Party.PartyTaxScheme.0.CompanyID.value', ''),
            // ctrans('texts.currency_id') => $this->resolveCurrencyId(data_get($this->invoice, 'DocumentCurrencyCode.value', $this->company->currency()->code)),
            ctrans('texts.contact_name') => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Name', ''),
            ctrans('texts.phone') => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.Telephone', ''),
            ctrans('texts.email') => data_get($this->invoice, 'AccountingCustomerParty.Party.Contact.ElectronicMail', ''),
        ]);
    }

    /**
     * Generates custom CSS column widths for the invoice line items table.
     *
     * @return string
     */
    private function customCss(): string
    {
        $css = '';
        $css .= "." . str_replace(" ", "", ctrans('texts.product_key')) . " { width: 15%;} ";
        $css .= "." . str_replace(" ", "", ctrans('texts.quantity')) . " { width: 8%;} ";
        $css .= "." . str_replace(" ", "", ctrans('texts.notes')) . " { width: 40%; } ";
        $css .= "." . str_replace(" ", "", ctrans('texts.cost')) . " { width:10%;} ";
        $css .= "." . str_replace(" ", "", ctrans('texts.tax')) . " { width:10%;} ";
        $css .= "." . str_replace(" ", "", ctrans('texts.line_total')) . " { width:15%;} ";

        return $css;

    }

    /**
     * Extracts document-level details (currency, type code, number, dates) and line items.
     *
     * @return array
     */
    private function invoiceDetails(): array
    {

        $data = $this->processValues([
            ctrans('texts.currency') => data_get($this->invoice, 'DocumentCurrencyCode.value', $this->company->currency()->code),
            ctrans('texts.currency_code') => data_get($this->invoice, 'InvoiceTypeCode.value', "380"),
            ctrans('texts.number') => data_get($this->invoice, 'ID.value', ''),
            ctrans('texts.date') => data_get($this->invoice, 'IssueDate', ''),
            ctrans('texts.due_date') => data_get($this->invoice, 'DueDate', ''),
        ]);

        $data['line_items'] = $this->invoiceLines();

        return $data;
    }

    /**
     * Extracts document metadata including currency, terms, and public notes.
     *
     * @return array
     */
    private function metadata(): array
    {

        return $this->processValues([
            'currency' => data_get($this->invoice, 'DocumentCurrencyCode.value', $this->company->currency()->code),
            ctrans('texts.terms') => $this->harvestTerms(),
            ctrans('texts.public_notes') => data_get($this->invoice, 'Note', ''),
        ]);
    }

    /**
     * Collects payment means and terms from the invoice into a newline-separated string.
     *
     * @return string
     */
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

    /**
     * Transforms Peppol InvoiceLine items into a formatted array for the PDF template.
     *
     * @return array
     */
    private function invoiceLines(): array
    {
        $lines = data_get($this->invoice, 'InvoiceLine', []);

        return array_map(function ($line) {
            return [
                ctrans('texts.product_key') => data_get($line, 'Item.Name', ''),
                // ctrans('texts.ocde') => data_get($line, 'InvoicedQuantity.UnitCode',''),
                ctrans('texts.quantity') => Number::formatValue(data_get($line, 'InvoicedQuantity.amount', 0), $this->company->currency()),
                ctrans('texts.notes') =>  data_get($line, 'Item.Description', ''),
                ctrans('texts.cost') => Number::formatValue(data_get($line, 'Price.PriceAmount.amount', 0), $this->company->currency()),
                'tax_name1' => data_get($line, 'Item.ClassifiedTaxCategory.0.TaxScheme.ID.value', ''),
                'tax_rate1' => data_get($line, 'Item.ClassifiedTaxCategory.0.Percent', 0),
                'tax_name2' => data_get($line, 'Item.ClassifiedTaxCategory.1.TaxScheme.ID.value', ''),
                'tax_rate2' => data_get($line, 'Item.ClassifiedTaxCategory.1.Percent', 0),
                'tax_name3' => data_get($line, 'Item.ClassifiedTaxCategory.2.TaxScheme.ID.value', ''),
                'tax_rate3' => data_get($line, 'Item.ClassifiedTaxCategory.2.Percent', 0),
                ctrans('texts.line_total') => Number::formatValue(data_get($line, 'LineExtensionAmount.amount', 0), $this->company->currency()),
            ];
        }, $lines);
    }

    /**
     * Extracts subtotals, tax breakdowns, and balance due from LegalMonetaryTotal and TaxTotal.
     *
     * @return array
     */
    private function totals(): array
    {
        $tax_inc = data_get($this->invoice, 'LegalMonetaryTotal.TaxInclusiveAmount.amount', 0);
        $tax_ex = data_get($this->invoice, 'LegalMonetaryTotal.TaxExclusiveAmount.amount', 0);
        $tax_amount = data_get($this->invoice, 'TaxTotal.0.TaxAmount', 0);

        $taxes = [];

        foreach (data_get($this->invoice, 'TaxTotal.0.TaxSubtotal', []) as $tax_subtotal) {
            $taxes[] = [
                'subtotal' => data_get($tax_subtotal, 'TaxAmount.amount', 0),
                'tax_name' => data_get($tax_subtotal, 'TaxCategory.TaxScheme.ID.value', ''),
                'tax_rate' => data_get($tax_subtotal, 'TaxCategory.Percent', 0),
            ];
        }

        return [
            'subtotal' => [
                ctrans('texts.subtotal') => data_get($this->invoice, 'LegalMonetaryTotal.LineExtensionAmount.amount', 0),
            ],
            'balance' => [
                ctrans('texts.balance_due') => data_get($this->invoice, 'LegalMonetaryTotal.TaxInclusiveAmount.amount', 0),
            ],
            'taxes' => $taxes,
        ];
    }

    //     private function resolveCountry(?string $iso_country_code): int
    //     {
    //         return Country::query()
    //                         ->where('iso_3166_2', $iso_country_code)
    //                         ->orWhere('iso_3166_3', $iso_country_code)
    //                         ->first()?->id ?? (int)$this->company->settings->country_id;
    //     }


    //     private function resolveCurrencyId(string $currency_code): int
    //     {

    //         /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
    //         $currencies = app('currencies');

    //         return $currencies->first(function (Currency $c) use ($currency_code) {
    //             return $c->code === $currency_code;
    //         })?->id ?? (int) $this->company->settings->currency_id;
    //     }
}
