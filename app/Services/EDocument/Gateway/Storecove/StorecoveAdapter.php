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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Standards\Peppol\CountryFactory;
use App\Services\EDocument\Gateway\Storecove\NexusResolver;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use App\Services\EDocument\Gateway\Storecove\Models\Credit;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class StorecoveAdapter
{
    public function __construct(public Storecove $storecove) {}

    private Invoice|Credit $storecove_invoice;

    private array $errors = [];

    private $ninja_invoice;

    private string $nexus;

    private bool $has_error = false;

    /**
     * Returns the transformed Storecove invoice model.
     *
     * @return Invoice
     */
    public function getInvoice(): Invoice
    {
        return $this->storecove_invoice;
    }

    /**
     * Returns the array of accumulated validation and transformation errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * addError
     *
     * Adds an error to the errors array.
     *
     * @param  string $error
     * @return self
     */
    private function addError(string $error): self
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * Deserializes a raw Storecove API response into a Storecove Invoice model.
     *
     * @param  array $storecove_object
     * @return Invoice
     */
    public function deserialize($storecove_object)
    {

        $context = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $serializer = $this->getSerializer();

        $obj['Invoice'] = $storecove_object['document']['invoice'];

        $storecove_object = $serializer->normalize($obj, null, [\Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);

        return $serializer->deserialize(json_encode($storecove_object), \App\Services\EDocument\Gateway\Storecove\Models\Invoice::class, 'json', $context);

    }

    /**
     * Transform a Ninja invoice/credit into a Storecove model by building a fresh Peppol document.
     *
     * @deprecated Use transformFromPeppol() to avoid double Peppol builds.
     * @param  \App\Models\Invoice|\App\Models\Credit $invoice
     * @return self
     */
    public function transform(\App\Models\Invoice|\App\Models\Credit $invoice): self
    {
        $peppol = (new Peppol($invoice))->run();
        return $this->transformFromPeppol($invoice, $peppol->getDocument(), $peppol->isCreditNote());
    }

    /**
     * Transform a pre-built Peppol document into a Storecove model.
     *
     * Serialization roundtrip: Peppol object → XML → decode → JSON → Storecove model.
     * This is required because the Storecove API JSON structure differs from Peppol UBL.
     *
     * @param  \App\Models\Invoice|\App\Models\Credit $invoice
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $peppolDocument
     * @param  bool $isCreditNote
     * @return self
     */
    public function transformFromPeppol(
        \App\Models\Invoice|\App\Models\Credit $invoice,
        \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $peppolDocument,
        bool $isCreditNote = false,
    ): self {
        try {
            $this->ninja_invoice = $invoice;
            $serializer = $this->getSerializer();

            $e = new \InvoiceNinja\EInvoice\EInvoice();
            $xml = $e->encode($peppolDocument, 'xml');

            // Wrap with proper XML namespace declarations
            if ($isCreditNote || $peppolDocument instanceof \InvoiceNinja\EInvoice\Models\Peppol\CreditNote) {
                $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<CreditNote xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2">';
                $suffix = '</CreditNote>';
            } else {
                $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';
                $suffix = '</Invoice>';
            }

            $xml = str_ireplace(['\n', '<?xml version="1.0"?>'], ['', $prefix], $xml);
            $xml .= $suffix;

            $context = [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ];

            $decoded = $e->decode('Peppol', $xml, 'xml');

            $parent = ($invoice instanceof \App\Models\Credit || $decoded instanceof \InvoiceNinja\EInvoice\Models\Peppol\CreditNote)
                ? Credit::class
                : Invoice::class;

            $encoded = $e->encode($decoded, 'json');
            $this->storecove_invoice = $serializer->deserialize($encoded, $parent, 'json', $context);


            $client_country_code = $invoice->client->country->iso_3166_2;
            $nexus_vat_number = isset($invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number) 
                                && strlen($invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number) > 1 
                                ? true
                                : false;
            
            if($nexus_vat_number){
                $this->storecove_invoice->setConsumerTaxMode(true);
            }

            $nexusResolver = new NexusResolver($invoice, $this->storecove_invoice, $this->storecove->router);
            $nexusResolver->resolve();

            $this->nexus = $nexusResolver->getNexus();

            foreach ($nexusResolver->getErrors() as $error) {
                $this->addError($error);
            }
        } catch (\Throwable $th) {

            $this->addError($th->getMessage());
            $this->has_error = true;
        }

        return $this;
    }

    /**
     * Returns the resolved tax nexus country code (ISO 3166-2).
     *
     * @return string
     */
    public function getNexus(): string
    {
        return $this->nexus;
    }

    /**
     * Decorates the Storecove invoice with tax nexus data, payment means codes,
     * allowance/charge adjustments, and customer public identifiers.
     *
     * @return self
     */
    public function decorate(): self
    {
        if ($this->has_error) {
            return $this;
        }

        //set all taxmap countries - resolve the taxing country
        $lines = $this->storecove_invoice->getInvoiceLines();

        foreach ($lines as &$line) {
            if (isset($line->taxes_duties_fees)) {
                foreach ($line->taxes_duties_fees as &$tax) {
                    $tax->country = $this->nexus;
                    $tax->percentage ??= 0;
                    if (property_exists($tax, 'category')) {
                        $tax->category = $this->transformTaxCode($tax->category);
                    }
                }
                unset($tax);
            }

            if (isset($line->allowance_charges)) {
                foreach ($line->allowance_charges as &$allowance) {
                    if ($allowance->reason == ctrans('texts.discount')) {
                        $allowance->amount_excluding_tax = $allowance->amount_excluding_tax * -1;
                    }


                    foreach ($allowance->getTaxesDutiesFees() ?? [] as &$tax) {

                        if (property_exists($tax, 'category')) {
                            $tax->category = $this->transformTaxCode($tax->category);
                        }

                    }
                    unset($tax);
                }
                unset($allowance);
            }
        }

        $this->storecove_invoice->setInvoiceLines($lines);

        $tax_subtotals = $this->storecove_invoice->getTaxSubtotals();

        foreach ($tax_subtotals as &$tax) {
            $tax->country = $this->nexus;
            $tax->percentage ??= 0;

            if (property_exists($tax, 'category')) {
                $tax->category = $this->transformTaxCode($tax->category);
            }

        }
        unset($tax);

        $this->storecove_invoice->setTaxSubtotals($tax_subtotals);
        //configure identifiers

        //update payment means codes to storecove equivalents
        $payment_means = $this->storecove_invoice->getPaymentMeansArray();

        foreach ($payment_means as &$pm) {
            $pm->code = $this->transformPaymentMeansCode($pm->code);
        }

        $this->storecove_invoice->setPaymentMeansArray($payment_means);

        $allowances = $this->storecove_invoice->getAllowanceCharges() ?? [];

        foreach ($allowances as &$allowance) {
            $taxes = $allowance->getTaxesDutiesFees() ?? [];

            foreach ($taxes as &$tax) {
                $tax->country = $this->nexus;
                $tax->percentage ??= 0;

                if (property_exists($tax, 'category')) {
                    $tax->category = $this->transformTaxCode($tax->category);
                }
            }
            unset($tax);


            if ($allowance->reason == ctrans('texts.discount')) {
                $allowance->amount_excluding_tax = $allowance->amount_excluding_tax * -1;
            }

            $allowance->setTaxesDutiesFees($taxes);

        }
        unset($allowance);

        $this->storecove_invoice->setAllowanceCharges($allowances);

        $this->storecove_invoice->setTaxSystem('tax_line_percentages');

        //resolve and set the public identifier for the customer
        $accounting_customer_party = $this->storecove_invoice->getAccountingCustomerParty();

        $client = $this->ninja_invoice->client;
        $country = $client->country->iso_3166_2;
        $router = $this->storecove->router;

        $handler = CountryFactory::make($country);
        $identifierPairs = $handler->storecoveCustomerPartyPublicIdentifiers($client, $this->ninja_invoice, $router);

        foreach ($identifierPairs as $pair) {
            $accounting_customer_party->addPublicIdentifiers(
                new \App\Services\EDocument\Gateway\Storecove\Models\PublicIdentifiers($pair['scheme'], $pair['id'])
            );
        }

        if (count($identifierPairs) > 0) {
            $this->storecove_invoice->setAccountingCustomerParty($accounting_customer_party);
        }

        $classification = $client->classification ?? 'business';

        // AT government: the supplier must be identified via customerAssignedAccountIdValue
        // on the accountingSupplierParty.party. Storecove uses this to look up the actual
        // recipient from the purchase order reference inside the document.
        if ($country === 'AT' && $classification === 'government') {
            $customer_assigned_account_id_value = trim($client->id_number ?? '');
            if (strlen($customer_assigned_account_id_value) > 1) {
                $supplier = $this->storecove_invoice->getAccountingSupplierParty();
                if ($supplier?->getParty()) {
                    $supplier->getParty()->setCustomerAssignedAccountIdValue($customer_assigned_account_id_value);
                    $this->storecove_invoice->setAccountingSupplierParty($supplier);
                }
            }
        }

        return $this;
    }

    /**
     * Builds a Symfony Serializer configured with Storecove-compatible
     * normalizers, name converters, and encoders.
     *
     * @return Serializer
     */
    private function getSerializer()
    {

        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $typeExtractors = [$reflectionExtractor,$phpDocExtractor];
        $descriptionExtractors = [$phpDocExtractor];
        $propertyInitializableExtractors = [$reflectionExtractor];
        $propertyInfo = new PropertyInfoExtractor(
            $propertyInitializableExtractors,
            $descriptionExtractors,
            $typeExtractors,
        );
        $xml_encoder = new XmlEncoder(['xml_format_output' => true, 'remove_empty_tags' => true,]);
        $json_encoder = new JsonEncoder();

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());

        $normalizer = new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter, null, $propertyInfo);

        $normalizers = [new DateTimeNormalizer(), $normalizer,  new ArrayDenormalizer()];
        $encoders = [$xml_encoder, $json_encoder];
        $serializer = new Serializer($normalizers, $encoders);

        return $serializer;
    }

    /**
     * Builds the document and appends an errors prop
     *
     * @return array
     */
    public function getDocument(): mixed
    {

        if ($this->has_error) {
            return ['errors' => $this->getErrors(), 'document' => false];
        }


        $serializer = $this->getSerializer();

        $context = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $s_invoice = $serializer->encode($this->storecove_invoice, 'json', $context);

        $s_invoice = json_decode($s_invoice, true);

        $s_invoice = $this->removeEmptyValues($s_invoice);

        $data = [
            'errors' => $this->getErrors(),
            'document' => $s_invoice,
        ];

        return $data;

    }

    /**
     * RemoveEmptyValues
     *
     * @param  array $array
     * @return array
     */
    private function removeEmptyValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === null || $value === '') {
                unset($array[$key]);
            }
        }
        // nlog($array);
        return $array;
    }

    // Nexus resolution logic has been extracted to NexusResolver class.

    /**
     * Maps a Peppol tax category code (e.g. 'S', 'Z', 'AE') to its
     * Storecove equivalent (e.g. 'standard', 'zero_rated', 'reverse_charge').
     *
     * @param  string $code
     * @return string|null
     */
    private function transformTaxCode(string $code): ?string
    {

        if ($code == 'O' && $this->ninja_invoice->client->classification == 'government') {
            return 'exempt';
        }

        // elseif($code == 'K' && $this->ninja_invoice->company->getSetting('classification') == 'individual')
        //     return 'reverse_charge';

        return match ($code) {
            'S' => 'standard',
            'Z' => 'zero_rated',
            'E' => 'exempt',
            'AE' => 'reverse_charge',
            'K' => 'intra_community',
            'G' => 'export',
            'O' => 'outside_scope',
            'L' => 'cgst',
            'I' => 'igst',
            'SS' => 'sgst',
            'B' => 'deemed_supply',
            'SR' => 'srca_s',
            'SC' => 'srca_c',
            'NR' => 'not_registered',
            default => null
        };
    }

    /**
     * Maps a UNCL4461 payment means code to its Storecove string equivalent
     * (e.g. '30' => 'credit_transfer', '48' => 'card').
     *
     * @param  string|null $code
     * @return string
     */
    private function transformPaymentMeansCode(?string $code): string
    {
        return match ($code) {
            '30' => 'credit_transfer',
            '58' => 'sepa_credit_transfer',
            '31' => 'debit_transfer',
            '49' => 'direct_debit',
            '59' => 'sepa_direct_debit',
            '48' => 'card',         // Generic card payment
            '54' => 'bank_card',
            '55' => 'credit_card',
            '57' => 'standing_agreement',
            '10' => 'cash',
            '20' => 'bank_cheque',
            '21' => 'cashiers_cheque',
            '97' => 'aunz_npp',
            '98' => 'aunz_npp_payid',
            '99' => 'aunz_npp_payto',
            '71' => 'aunz_bpay',
            '72' => 'aunz_postbillpay',
            '73' => 'aunz_uri',
            '50' => 'se_bankgiro',
            '51' => 'se_plusgiro',
            '74' => 'sg_giro',
            '75' => 'sg_card',
            '76' => 'sg_paynow',
            '77' => 'it_mav',
            '78' => 'it_pagopa',
            '42' => 'nl_ga_beneficiary',
            '43' => 'nl_ga_gaccount',
            '1'  => 'undefined',    // Instrument not defined
            default => 'undefined',
        };

    }

}
