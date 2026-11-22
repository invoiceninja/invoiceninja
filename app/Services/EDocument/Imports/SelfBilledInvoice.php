<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Imports;

use App\Events\Invoice\InvoiceWasCreated;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Utils\Ninja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use UnexpectedValueException;

class SelfBilledInvoice
{
    public static function isSelfBilledInvoice(string $xml): bool
    {
        if (trim($xml) === '') {
            return false;
        }

        $previous_errors = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();

            if (!$document->loadXML($xml, LIBXML_NONET) || $document->doctype) {
                return false;
            }

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
            $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

            return $xpath->evaluate('normalize-space((//inv:Invoice/cbc:InvoiceTypeCode)[1])') === '389';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_errors);
        }
    }

    public function createInvoice(PeppolInvoice $document, Company $company, ?string $received_guid = null): Invoice
    {
        $party = data_get($document, 'AccountingCustomerParty.Party');
        $source_number = trim((string) data_get($document, 'ID.value', ''));

        if (!$party || $source_number === '' || !data_get($document, 'IssueDate')) {
            throw new UnexpectedValueException('Self-billed invoice requires a buyer, invoice number and issue date.');
        }

        $client_data = $this->clientData($party, $document);
        $buyer_identifier = $client_data['routing_id'] ?: $client_data['vat_number'] ?: $client_data['id_number'];

        if ($buyer_identifier === '') {
            throw new UnexpectedValueException('Self-billed invoice requires a buyer identifier.');
        }

        $source_document = $this->normalizeDocument($document);
        $source_document['ID']['value'] = $source_number;
        $source_document['InvoiceTypeCode']['value'] = 389;

        return DB::transaction(function () use ($document, $company, $client_data, $source_number, $source_document, $received_guid) {
            $client = $this->findOrCreateClient($client_data, $company);
            $existing = Invoice::withTrashed()
                ->where('company_id', $company->id)
                ->where(function ($query) use ($client, $source_number, $received_guid) {
                    $query->where(function ($query) use ($client, $source_number) {
                        $query->where('client_id', $client->id)
                            ->where('e_invoice->Invoice->ID->value', $source_number)
                            ->whereIn('e_invoice->Invoice->InvoiceTypeCode->value', [389, '389']);
                    });

                    if ($received_guid) {
                        $query->orWhereHas('documents', function ($query) use ($received_guid) {
                            $query->where('name', $received_guid . '.xml');
                        });
                    }
                })
                ->first();

            if ($existing) {
                if (json_encode($this->normalizeDocument($existing->e_invoice->Invoice)) !== json_encode($source_document)) {
                    throw new UnexpectedValueException('Self-billed invoice identity already exists with different document contents.');
                }

                $guid = $received_guid ?: $existing->backup->guid ?: trim((string) data_get($document, 'UUID.value', '')) ?: Str::uuid()->toString();

                if ($existing->backup->guid !== $guid) {
                    $existing->backup->guid = $guid;
                    $existing->saveQuietly();
                }

                return $existing;
            }

            $invoice = InvoiceFactory::create($company->id, $company->owner()->id);
            $invoice->e_invoice = ['Invoice' => $source_document];
            $invoice->backup->guid = $received_guid ?: trim((string) data_get($document, 'UUID.value', '')) ?: Str::uuid()->toString();

            $invoice_data = [
                'client_id' => $client->id,
                'number' => Invoice::withTrashed()->where('company_id', $company->id)->where('number', $source_number)->exists() ? null : $source_number,
                'date' => data_get($document, 'IssueDate')?->format('Y-m-d'),
                'due_date' => data_get($document, 'DueDate')?->format('Y-m-d'),
                'po_number' => data_get($document, 'OrderReference.ID.value', ''),
                'public_notes' => data_get($document, 'Note', ''),
                'private_notes' => 'Self-billed invoice: ' . $source_number,
                'terms' => data_get($document, 'PaymentTerms.0.Note', ''),
                'uses_inclusive_taxes' => false,
                'auto_bill_enabled' => false,
                'line_items' => $this->lineItems($document),
            ];

            $invoice = (new InvoiceRepository())->save($invoice_data, $invoice);
            // Client defaults must not replace notes supplied on the received document.
            $invoice->public_notes = $invoice_data['public_notes'];
            $invoice->saveQuietly();

            $expected_total = data_get($document, 'LegalMonetaryTotal.TaxInclusiveAmount.amount');
            $rounding_amount = (float) data_get($document, 'LegalMonetaryTotal.PayableRoundingAmount.amount', 0);
            $precision = $client->currency()->precision;

            if ($expected_total === null || round($invoice->amount - (float) $expected_total - $rounding_amount, $precision) != 0) {
                throw new UnexpectedValueException('Self-billed invoice totals do not match the received document.');
            }

            event(new InvoiceWasCreated($invoice, $company, Ninja::eventVars($invoice->user_id)));

            return $invoice;
        });
    }

    private function normalizeDocument(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            $value = array_map(fn ($value) => $this->normalizeDocument($value), $value);

            if (!array_is_list($value)) {
                ksort($value);
            }
        }

        return $value;
    }

    private function clientData(object $party, PeppolInvoice $document): array
    {
        $vat_number = '';

        foreach (data_get($party, 'PartyTaxScheme', []) as $tax_scheme) {
            if (data_get($tax_scheme, 'TaxScheme.ID.value') === 'VAT') {
                $vat_number = (string) data_get($tax_scheme, 'CompanyID.value', '');
                break;
            }
        }

        $currency_code = strtoupper(trim((string) data_get($document, 'DocumentCurrencyCode.value')));

        /** @var ?\App\Models\Currency $currency */
        $currency = app('currencies')->firstWhere('code', $currency_code);
        $country_code = strtoupper(trim((string) data_get($party, 'PostalAddress.Country.IdentificationCode.value')));

        /** @var ?\App\Models\Country $country */
        $country = app('countries')->first(fn (\App\Models\Country $country) =>
            $country->iso_3166_2 === $country_code || $country->iso_3166_3 === $country_code);

        if (!$currency || $country_code === '' || !$country) {
            throw new UnexpectedValueException('Self-billed invoice has an unsupported currency or buyer country.');
        }

        return [
            'name' => data_get($party, 'PartyLegalEntity.0.RegistrationName') ?: data_get($party, 'PartyName.0.Name', ''),
            'vat_number' => $vat_number,
            'id_number' => (string) data_get($party, 'PartyLegalEntity.0.CompanyID.value', ''),
            'routing_id' => (string) data_get($party, 'EndpointID.value', ''),
            'address1' => data_get($party, 'PostalAddress.StreetName', ''),
            'address2' => data_get($party, 'PostalAddress.AdditionalStreetName', ''),
            'city' => data_get($party, 'PostalAddress.CityName', ''),
            'state' => data_get($party, 'PostalAddress.CountrySubentity', ''),
            'postal_code' => data_get($party, 'PostalAddress.PostalZone', ''),
            'country_id' => $country->id,
            'settings' => ['currency_id' => (string) $currency->id],
            'contacts' => [[
                'first_name' => data_get($party, 'Contact.Name', ''),
                'email' => data_get($party, 'Contact.ElectronicMail', ''),
                'phone' => data_get($party, 'Contact.Telephone', ''),
            ]],
        ];
    }

    private function findOrCreateClient(array $client_data, Company $company): Client
    {
        $clients = Client::withTrashed()
            ->where('company_id', $company->id)
            ->where('is_deleted', false)
            ->where(function ($query) use ($client_data) {
                foreach (['vat_number', 'id_number', 'routing_id'] as $field) {
                    if ($client_data[$field] !== '') {
                        $query->orWhere($field, $client_data[$field]);
                    }
                }
            })->get();

        if ($clients->count() > 1) {
            throw new UnexpectedValueException('Self-billed invoice buyer matches multiple clients.');
        }

        if ($client = $clients->first()) {
            if ((string) $client->getSetting('currency_id') !== $client_data['settings']['currency_id']) {
                throw new UnexpectedValueException('Self-billed invoice currency differs from the existing client currency.');
            }

            return $client;
        }

        return (new ClientRepository(new ClientContactRepository()))->save(
            $client_data,
            ClientFactory::create($company->id, $company->owner()->id),
        );
    }

    private function lineItems(PeppolInvoice $document): array
    {
        $items = [];

        foreach (data_get($document, 'InvoiceLine', []) as $line) {
            $item = InvoiceItemFactory::create();
            $item->product_key = data_get($line, 'Item.Name', '');
            $item->notes = data_get($line, 'Item.Description', '');
            $item->quantity = (float) data_get($line, 'InvoicedQuantity.amount', 0);
            $base_quantity = (float) data_get($line, 'Price.BaseQuantity.amount', 1);

            if ($base_quantity <= 0) {
                throw new UnexpectedValueException('Self-billed invoice price base quantity must be positive.');
            }

            $item->cost = (float) data_get($line, 'Price.PriceAmount.amount', 0) / $base_quantity;

            foreach (data_get($line, 'AllowanceCharge', []) as $allowance) {
                $is_charge = filter_var(data_get($allowance, 'ChargeIndicator', false), FILTER_VALIDATE_BOOLEAN);
                $item->discount += (float) data_get($allowance, 'Amount.amount', 0) * ($is_charge ? -1 : 1);
            }

            $this->applyTaxes($item, data_get($line, 'Item.ClassifiedTaxCategory', []));
            $items[] = $item;
        }

        foreach (data_get($document, 'AllowanceCharge', []) as $allowance) {
            $item = InvoiceItemFactory::create();
            $item->product_key = data_get($allowance, 'AllowanceChargeReason', '');
            $item->quantity = 1;
            $is_charge = filter_var(data_get($allowance, 'ChargeIndicator', false), FILTER_VALIDATE_BOOLEAN);
            $item->cost = (float) data_get($allowance, 'Amount.amount', 0) * ($is_charge ? 1 : -1);
            $this->applyTaxes($item, data_get($allowance, 'TaxCategory', []));
            $items[] = $item;
        }

        $rounding_amount = (float) data_get($document, 'LegalMonetaryTotal.PayableRoundingAmount.amount', 0);

        if ($rounding_amount != 0) {
            $item = InvoiceItemFactory::create();
            $item->product_key = 'Rounding';
            $item->quantity = 1;
            $item->cost = $rounding_amount;
            $items[] = $item;
        }

        if (!$items) {
            throw new UnexpectedValueException('Self-billed invoice requires invoice lines.');
        }

        return $items;
    }

    private function applyTaxes(object $item, array $categories): void
    {
        if (count($categories) > 3) {
            throw new UnexpectedValueException('Self-billed invoice has more than three taxes on a line.');
        }

        foreach ($categories as $index => $category) {
            $tax_level = $index + 1;
            $item->{"tax_name{$tax_level}"} = data_get($category, 'TaxScheme.ID.value', 'VAT');
            $item->{"tax_rate{$tax_level}"} = (float) data_get($category, 'Percent', 0);
        }
    }
}
