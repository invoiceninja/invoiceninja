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

use App\DataMapper\FranceEReporting\FRReportData;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final class FranceEReportStorecoveProjection
{
    /**
     * Keep the public Storecove request independent from relay metadata.
     *
     * @param array<string, mixed> $payload
     * @return array{legalEntityId: int, idempotencyGuid: string, document: array<string, mixed>}
     */
    public static function from(array $payload): array
    {
        $legalEntityId = $payload['legalEntityId'] ?? null;
        $idempotencyGuid = $payload['idempotencyGuid'] ?? null;
        $document = $payload['document'] ?? null;

        if (! is_int($legalEntityId) || $legalEntityId < 1) {
            throw new InvalidArgumentException('France e-report legalEntityId must be a positive integer.');
        }

        if (! is_string($idempotencyGuid) || ! Uuid::isValid($idempotencyGuid)) {
            throw new InvalidArgumentException('France e-report idempotencyGuid must be a valid UUID.');
        }

        if (! is_array($document)
            || ($document['documentType'] ?? null) !== 'fr_e_report'
            || ! is_array($document['frEReport'] ?? null)) {
            throw new InvalidArgumentException('France e-report document must use the Storecove fr_e_report shape.');
        }

        self::validateDocument($document);

        return [
            'legalEntityId' => $legalEntityId,
            'idempotencyGuid' => $idempotencyGuid,
            'document' => $document,
        ];
    }

    /** @param array<string, mixed> $document */
    private static function validateDocument(array $document): void
    {
        self::assertOnlyKeys($document, ['documentType', 'frEReport'], 'document');
        self::assertNoNulls($document, 'document');
        $report = $document['frEReport'];
        self::assertOnlyKeys($report, [
            'typeCode',
            'documentId',
            'issueDate',
            'issueTime',
            'timeZone',
            'declarantParty',
            'transactionReport',
            'paymentReport',
        ], 'frEReport');

        if (array_key_exists('transactionReport', $report) === array_key_exists('paymentReport', $report)) {
            throw new InvalidArgumentException('Exactly one non-null transactionReport or paymentReport is required.');
        }

        $typedReport = FRReportData::fromArray($report);

        self::validateReportTiming($typedReport);
        self::validateTransitionalInvoiceIds($typedReport);

        self::validateDeclarant($report['declarantParty']);

        if (isset($report['transactionReport'])) {
            self::validateTransactionReport($report['transactionReport']);
        } else {
            self::validatePaymentReport($report['paymentReport']);
        }
    }

    /** @param array<string, mixed> $declarant */
    private static function validateDeclarant(array $declarant): void
    {
        self::assertOnlyKeys($declarant, ['publicIdentifiers', 'party'], 'frEReport.declarantParty');
        self::assertList($declarant['publicIdentifiers'], 'frEReport.declarantParty.publicIdentifiers');

        if (count($declarant['publicIdentifiers']) !== 1
            || ($declarant['publicIdentifiers'][0]['scheme'] ?? null) !== 'FR:SIRENE'
            || ! is_string($declarant['publicIdentifiers'][0]['id'] ?? null)
            || ! (new StorecoveIdentifierValidator())->validFormat('FR:SIRENE', $declarant['publicIdentifiers'][0]['id'])) {
            throw new InvalidArgumentException('France e-report declarant must contain one valid FR:SIRENE identifier.');
        }

        foreach ($declarant['publicIdentifiers'] as $identifier) {
            self::assertOnlyKeys($identifier, ['scheme', 'id'], 'frEReport.declarantParty.publicIdentifiers.*');
        }

        if (! is_array($declarant['party'] ?? null)
            || ! is_string($declarant['party']['companyName'] ?? null)
            || trim($declarant['party']['companyName']) === '') {
            throw new InvalidArgumentException('France e-report declarant companyName is required.');
        }

        self::assertOnlyKeys($declarant['party'], ['companyName'], 'frEReport.declarantParty.party');
    }

    /** @param array<string, mixed> $report */
    private static function validateTransactionReport(array $report): void
    {
        self::assertOnlyKeys($report, ['period', 'b2biInvoices', 'b2cTransactions'], 'transactionReport');
        self::assertPresentRowsAreNotEmpty($report, ['b2biInvoices', 'b2cTransactions'], 'transactionReport');

        foreach ($report['b2biInvoices'] ?? [] as $invoice) {
            self::assertOnlyKeys($invoice, [
                'invoiceNumber',
                'issueDate',
                'dueDate',
                'documentCurrency',
                'amountIncludingVat',
                'accountingSupplierParty',
                'accountingCustomerParty',
                'taxSubtotals',
                'invoiceLines',
            ], 'transactionReport.b2biInvoices.*');
            self::validateAccountingParty($invoice['accountingSupplierParty'], 'accountingSupplierParty');
            self::validateAccountingParty($invoice['accountingCustomerParty'], 'accountingCustomerParty');
            self::validateRows($invoice['taxSubtotals'], [
                'taxCategory',
                'percentage',
                'taxableAmount',
                'taxAmount',
                'country',
            ], 'transactionReport.b2biInvoices.*.taxSubtotals');

            foreach ($invoice['invoiceLines'] as $line) {
                self::assertOnlyKeys($line, ['description', 'amountExcludingVat', 'tax'], 'transactionReport.b2biInvoices.*.invoiceLines.*');
                self::assertOnlyKeys($line['tax'], ['percentage', 'category', 'country'], 'transactionReport.b2biInvoices.*.invoiceLines.*.tax');
            }
        }

        self::validateRows($report['b2cTransactions'] ?? [], [
            'date',
            'category',
            'currency',
            'amountExcludingVat',
            'amountIncludingVat',
            'transactionsCount',
            'vatPaymentOption',
            'taxSubtotals',
        ], 'transactionReport.b2cTransactions');

        foreach ($report['b2cTransactions'] ?? [] as $transaction) {
            self::validateRows($transaction['taxSubtotals'], [
                'category',
                'percentage',
                'taxableAmount',
                'taxAmount',
            ], 'transactionReport.b2cTransactions.*.taxSubtotals');
        }
    }

    /** @param array<string, mixed> $report */
    private static function validatePaymentReport(array $report): void
    {
        self::assertOnlyKeys($report, ['period', 'b2biPayments', 'b2cPayments'], 'paymentReport');
        self::assertPresentRowsAreNotEmpty($report, ['b2biPayments', 'b2cPayments'], 'paymentReport');
        self::validateRows($report['b2biPayments'] ?? [], [
            'invoiceNumber',
            'issueDate',
            'paymentDate',
            'paymentMeansCode',
            'taxSubtotals',
        ], 'paymentReport.b2biPayments');

        foreach ($report['b2biPayments'] ?? [] as $payment) {
            self::validateRows($payment['taxSubtotals'], [
                'percentage',
                'category',
                'currency',
                'country',
                'amountIncludingTax',
            ], 'paymentReport.b2biPayments.*.taxSubtotals');
        }

        self::validateRows($report['b2cPayments'] ?? [], ['date', 'taxSubtotal'], 'paymentReport.b2cPayments');

        foreach ($report['b2cPayments'] ?? [] as $payment) {
            self::validateRows($payment['taxSubtotal'], [
                'category',
                'percentage',
                'country',
                'currency',
                'amount',
            ], 'paymentReport.b2cPayments.*.taxSubtotal');
        }
    }

    /** @param array<string, mixed> $accountingParty */
    private static function validateAccountingParty(array $accountingParty, string $field): void
    {
        self::assertOnlyKeys($accountingParty, ['party', 'publicIdentifiers'], $field);
        self::assertOnlyKeys($accountingParty['party'], ['companyName', 'address'], "{$field}.party");
        self::assertOnlyKeys($accountingParty['party']['address'], ['street1', 'street2', 'zip', 'city', 'country'], "{$field}.party.address");
        self::validateRows($accountingParty['publicIdentifiers'], ['scheme', 'id'], "{$field}.publicIdentifiers");
    }

    private static function validateReportTiming(FRReportData $report): void
    {
        $period = ($report->transactionReport ?? $report->paymentReport)->period;
        $periodEnd = substr($period, -10);

        if ($report->issueDate <= $periodEnd) {
            throw new InvalidArgumentException('France e-report issue date must be after its period end.');
        }

        $issuedAt = CarbonImmutable::parse($report->issueDate.' '.$report->issueTime.' '.$report->timeZone);

        if ($issuedAt->setTimezone('Europe/Paris')->format('O') !== $report->timeZone) {
            throw new InvalidArgumentException('France e-report timeZone must match Europe/Paris on its issue date.');
        }
    }

    private static function validateTransitionalInvoiceIds(FRReportData $report): void
    {
        if (CarbonImmutable::now('Europe/Paris')->toDateString() > '2026-12-31') {
            return;
        }

        if ($report->transactionReport) {
            foreach ($report->transactionReport->b2biInvoices as $invoice) {
                if (mb_strlen($invoice->invoiceNumber) > 20) {
                    throw new InvalidArgumentException('b2biInvoices.invoiceNumber exceeds the transitional 20-character Storecove limit.');
                }
            }
        }

        if ($report->paymentReport) {
            foreach ($report->paymentReport->b2biPayments as $payment) {
                if (mb_strlen($payment->invoiceNumber) > 20) {
                    throw new InvalidArgumentException('b2biPayments.invoiceNumber exceeds the transitional 20-character Storecove limit.');
                }
            }
        }
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<int, string> $allowed
     */
    private static function validateRows(array $rows, array $allowed, string $field): void
    {
        self::assertList($rows, $field);

        foreach ($rows as $row) {
            self::assertOnlyKeys($row, $allowed, "{$field}.*");
        }
    }

    /** @param array<string, mixed> $data @param array<int, string> $allowed */
    private static function assertOnlyKeys(array $data, array $allowed, string $field): void
    {
        $unknown = array_diff(array_keys($data), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException("{$field} contains unsupported fields: ".implode(', ', $unknown).'.');
        }
    }

    /** @param array<int, mixed> $data */
    private static function assertList(array $data, string $field): void
    {
        if (! array_is_list($data)) {
            throw new InvalidArgumentException("{$field} must be a list.");
        }
    }

    /** @param array<string, mixed> $data @param array<int, string> $rowFields */
    private static function assertPresentRowsAreNotEmpty(array $data, array $rowFields, string $field): void
    {
        foreach ($rowFields as $rowField) {
            if (array_key_exists($rowField, $data) && $data[$rowField] === []) {
                throw new InvalidArgumentException("{$field}.{$rowField} must be omitted when empty.");
            }
        }
    }

    /** @param array<string|int, mixed> $data */
    private static function assertNoNulls(array $data, string $field): void
    {
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                throw new InvalidArgumentException("{$field}.{$key} must be omitted when null.");
            }

            if (is_array($value)) {
                self::assertNoNulls($value, "{$field}.{$key}");
            }
        }
    }
}
