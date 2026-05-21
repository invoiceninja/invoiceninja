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

namespace App\Services\EDocument\Standards\France;

use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\Models\Credit;
use App\Models\Invoice;
use App\Services\EDocument\Standards\France\Models\B2BIInvoice;
use App\Services\EDocument\Standards\Peppol;
use InvoiceNinja\EInvoice\EInvoice;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class FranceReportEntryBuilder
{
    public function b2biInvoice(Invoice|Credit $invoice): B2BIInvoiceData
    {
        $context = [
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $peppolInvoice = (new Peppol($invoice))->run()->getDocument();
        $peppolJson = (new EInvoice())->encode($peppolInvoice, 'json', $context);
        $b2biInvoice = $this->serializer()->deserialize($peppolJson, B2BIInvoice::class, 'json', $context);
        $payload = $this->removeEmptyValues($b2biInvoice->toArray());

        if ($invoice instanceof Credit) {
            $payload = $this->negativeInvoicePayload($payload);
        }

        return B2BIInvoiceData::fromArray($payload);
    }

    private function serializer(): Serializer
    {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor(
            [$reflectionExtractor],
            [$phpDocExtractor],
            [$reflectionExtractor, $phpDocExtractor],
        );

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, $propertyInfo);

        return new Serializer(
            [new DateTimeNormalizer(), $normalizer, new ArrayDenormalizer()],
            [new XmlEncoder(['xml_format_output' => true, 'remove_empty_tags' => true]), new JsonEncoder()],
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function negativeInvoicePayload(array $payload): array
    {
        foreach (['amountIncludingVat'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = $this->negativeAmount($payload[$key]);
            }
        }

        foreach ($payload['taxSubtotals'] ?? [] as $index => $taxSubtotal) {
            foreach (['taxableAmount', 'taxAmount', 'amountIncludingTax'] as $key) {
                if (array_key_exists($key, $taxSubtotal)) {
                    $payload['taxSubtotals'][$index][$key] = $this->negativeAmount($taxSubtotal[$key]);
                }
            }
        }

        foreach ($payload['invoiceLines'] ?? [] as $index => $invoiceLine) {
            if (array_key_exists('amountExcludingVat', $invoiceLine)) {
                $payload['invoiceLines'][$index]['amountExcludingVat'] = $this->negativeAmount($invoiceLine['amountExcludingVat']);
            }
        }

        return $payload;
    }

    private function negativeAmount(int|float|string $amount): int|float|string
    {
        if (is_int($amount)) {
            return $amount === 0 ? 0 : -abs($amount);
        }

        if (is_float($amount)) {
            return $amount == 0.0 ? 0.0 : -abs($amount);
        }

        $amount = trim($amount);

        if ($amount === '' || str_starts_with($amount, '-') || (float) $amount == 0.0) {
            return $amount;
        }

        return '-'.$amount;
    }
    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function removeEmptyValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
            }

            if ($array[$key] === [] || $array[$key] === '' || is_null($array[$key])) {
                unset($array[$key]);
            }
        }

        return $array;
    }
}