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
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
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
        $peppolJson = (new EInvoice())->encode($peppolInvoice, 'json');
        $b2biInvoice = $this->serializer()->deserialize($peppolJson, B2BIInvoice::class, 'json', $context);
        $payload = $this->removeEmptyValues($b2biInvoice->toArray());

        if ($invoice instanceof Credit) {
            $payload = $this->negativeInvoicePayload($payload);
        }

        return B2BIInvoiceData::fromArray($payload);
    }

    public function b2cTransaction(Invoice|Credit $document): B2CTransactionData
    {
        $calc = $document->calc();

        return new B2CTransactionData(
            date: (string) ($document->date ?: now()->toDateString()),
            category: $this->b2cCategory($document),
            currency: $this->currencyCode($document),
            amountExcludingVat: $this->signedDocumentAmount($calc->getNetSubtotal(), $document),
            amountIncludingVat: $this->signedDocumentAmount($document->amount ?: $calc->getTotal(), $document),
            transactionsCount: 1,
            vatPaymentOption: 'customer',
            taxSubtotals: $this->transactionTaxSubtotals($document),
        );
    }

    public function b2biPayment(Payment $payment, Invoice $invoice, int|float|string|null $paymentAmount = null, ?string $paymentDate = null): B2BIPaymentData
    {
        $amount = $paymentAmount ?? $this->paymentAmountForInvoice($payment, $invoice);

        return new B2BIPaymentData(
            invoiceNumber: (string) $invoice->number,
            paymentDate: (string) ($paymentDate ?: $payment->date ?: now()->toDateString()),
            issueDate: (string) ($invoice->date ?: now()->toDateString()),
            paymentMeansCode: $this->paymentMeansCode($payment),
            taxSubtotals: $this->paymentTaxSubtotals($invoice, $amount, true),
        );
    }

    public function b2cPayment(Payment $payment, Invoice $invoice, int|float|string|null $paymentAmount = null, ?string $paymentDate = null): B2CPaymentData
    {
        return new B2CPaymentData(
            date: (string) ($paymentDate ?: $payment->date ?: now()->toDateString()),
            taxSubtotal: $this->paymentTaxSubtotals($invoice, $paymentAmount ?? $this->paymentAmountForInvoice($payment, $invoice), false),
        );
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
     * @return array<int, TaxSubtotalData>
     */
    private function transactionTaxSubtotals(Invoice|Credit $document): array
    {
        $calc = $document->calc();
        $taxes = $calc->getTaxMap()->merge($calc->getTotalTaxMap())->values();

        if ($taxes->isEmpty()) {
            return [
                new TaxSubtotalData(
                    percentage: '0',
                    category: 'exempt',
                    taxableAmount: $this->signedDocumentAmount($calc->getNetSubtotal(), $document),
                    taxAmount: '0',
                    currency: $this->currencyCode($document),
                ),
            ];
        }

        return $taxes->map(function (array $tax) use ($document, $calc): TaxSubtotalData {
            $taxRate = $tax['tax_rate'] ?? 0;
            $taxableAmount = $tax['base_amount'] ?? $calc->getNetSubtotal();
            $taxAmount = $tax['total'] ?? 0;

            return new TaxSubtotalData(
                percentage: $this->normalizeAmount($taxRate),
                category: $this->taxCategory($taxRate),
                taxableAmount: $this->signedDocumentAmount($taxableAmount, $document),
                taxAmount: $this->signedDocumentAmount($taxAmount, $document),
                currency: $this->currencyCode($document),
            );
        })->all();
    }

    /**
     * @return array<int, TaxSubtotalData>
     */
    private function paymentTaxSubtotals(Invoice $invoice, int|float|string $paymentAmount, bool $amountIncludingTaxOnly): array
    {
        $calc = $invoice->calc();
        $taxes = $calc->getTaxMap()->merge($calc->getTotalTaxMap())->values();
        $ratio = $this->paymentRatio($invoice, $paymentAmount);
        $currency = $this->currencyCode($invoice);
        $sign = (float) $paymentAmount < 0 ? -1 : 1;

        if ($taxes->isEmpty()) {
            return [
                new TaxSubtotalData(
                    percentage: '0',
                    category: 'exempt',
                    taxableAmount: $amountIncludingTaxOnly ? null : $this->normalizeAmount((float) $calc->getNetSubtotal() * $ratio * $sign),
                    taxAmount: $amountIncludingTaxOnly ? null : '0',
                    currency: $currency,
                    country: 'FR',
                    amountIncludingTax: $amountIncludingTaxOnly ? $this->normalizeAmount($paymentAmount) : null,
                ),
            ];
        }

        return $taxes->map(function (array $tax) use ($calc, $ratio, $currency, $amountIncludingTaxOnly, $paymentAmount, $sign): TaxSubtotalData {
            $taxRate = $tax['tax_rate'] ?? 0;
            $taxableAmount = (float) ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $ratio * $sign;
            $taxAmount = (float) ($tax['total'] ?? 0) * $ratio * $sign;

            return new TaxSubtotalData(
                percentage: $this->normalizeAmount($taxRate),
                category: $this->taxCategory($taxRate),
                taxableAmount: $amountIncludingTaxOnly ? null : $this->normalizeAmount($taxableAmount),
                taxAmount: $amountIncludingTaxOnly ? null : $this->normalizeAmount($taxAmount),
                currency: $currency,
                country: 'FR',
                amountIncludingTax: $amountIncludingTaxOnly ? $this->normalizeAmount($paymentAmount) : null,
            );
        })->all();
    }

    private function paymentAmountForInvoice(Payment $payment, Invoice $invoice): int|float
    {
        return $this->normalizeAmount($invoice->amount ?? $payment->amount ?? 0);
    }

    private function paymentRatio(Invoice $invoice, int|float|string $paymentAmount): float
    {
        $invoiceAmount = (float) ($invoice->amount ?: 0);

        if ($invoiceAmount == 0.0) {
            return 1.0;
        }

        return abs((float) $paymentAmount) / abs($invoiceAmount);
    }

    private function paymentMeansCode(Payment $payment): ?string
    {
        return match ((int) $payment->type_id) {
            Payment::TYPE_BANK_TRANSFER, Payment::TYPE_SEPA, Payment::TYPE_GOCARDLESS => '30',
            Payment::TYPE_CREDIT_CARD, Payment::TYPE_APPLE_PAY => '48',
            default => null,
        };
    }

    private function b2cCategory(Invoice|Credit $document): string
    {
        $category = (string) ($document->company->getSetting('france_b2c_transaction_category') ?: 'TLB1');

        return trim($category) !== '' ? $category : 'TLB1';
    }

    private function currencyCode(Invoice|Credit $model): string
    {
        return $model->client->currency()->code;
    }

    private function taxCategory(int|float|string $taxRate): string
    {
        return (float) $taxRate > 0 ? 'standard' : 'exempt';
    }

    private function signedDocumentAmount(int|float|string $amount, Invoice|Credit $document): int|float
    {
        $amount = $this->normalizeAmount($amount);

        if ($document instanceof Credit) {
            return $this->negativeAmount($amount);
        }

        return $amount;
    }

    private function normalizeAmount(int|float|string|null $amount): int|float
    {
        $amount = round((float) ($amount ?? 0), 2);

        if (abs($amount - (int) $amount) < 0.00001) {
            return (int) $amount;
        }

        return $amount;
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