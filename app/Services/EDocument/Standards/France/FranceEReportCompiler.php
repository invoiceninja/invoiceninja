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
use App\DataMapper\FranceEReporting\DeclarantPartyData;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\FranceEReporting\PartyData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\PublicIdentifierData;
use App\DataMapper\FranceEReporting\ReportDataValidator;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\Models\Company;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use App\Utils\BcMath;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class FranceEReportCompiler
{
    /** @param iterable<int, FRReportEntryData> $entries */
    public function compileVariant(
        Company $company,
        FranceEReportVariant $variant,
        string $periodEnd,
        iterable $entries,
        ?CarbonImmutable $issuedAt = null,
        ?string $documentId = null,
    ): FRReportData {
        $issuedAt ??= CarbonImmutable::now('Europe/Paris');

        return $this->compileVariantFromEntries(
            $company,
            $variant,
            $this->contextForVariant($company, $variant, $periodEnd, $issuedAt),
            $entries,
            $documentId,
        );
    }

    /**
     * Compile a runtime projection without coupling the compiler to persistence.
     *
     * @param iterable<int, mixed> $entries
     */
    public function compileVariantFromEntries(
        Company $company,
        FranceEReportVariant $variant,
        FranceEReportContext $context,
        iterable $entries,
        ?string $documentId = null,
    ): FRReportData {
        $this->validateContext($company, $context);

        $b2biInvoices = [];
        $b2cTransactions = [];
        $b2biPayments = [];
        $b2cPayments = [];

        foreach ($entries as $entry) {
            if (! $entry instanceof FRReportEntryData) {
                throw new InvalidArgumentException('France e-report runtime projection requires FRReportEntryData values.');
            }

            if ($variant->isTransaction()) {
                if ($entry->b2biInvoice) {
                    $b2biInvoices[] = $entry->b2biInvoice;
                    continue;
                }

                if ($entry->b2cTransaction) {
                    $b2cTransactions[] = $entry->b2cTransaction;
                    continue;
                }

                throw new InvalidArgumentException("Runtime entry is incompatible with {$variant->value}.");
            }

            if ($entry->b2biPayment) {
                $b2biPayments[] = $entry->b2biPayment;
                continue;
            }

            if ($entry->b2cPayment) {
                $b2cPayments[] = $entry->b2cPayment;
                continue;
            }

            throw new InvalidArgumentException("Runtime entry is incompatible with {$variant->value}.");
        }

        return $this->compileTypedEntries(
            company: $company,
            variant: $variant,
            context: $context,
            b2biInvoices: $b2biInvoices,
            b2cTransactions: $b2cTransactions,
            b2biPayments: $b2biPayments,
            b2cPayments: $b2cPayments,
            documentId: $documentId,
        );
    }

    /**
     * @param array<int, B2BIInvoiceData> $b2biInvoices
     * @param array<int, B2CTransactionData> $b2cTransactions
     * @param array<int, B2BIPaymentData> $b2biPayments
     * @param array<int, B2CPaymentData> $b2cPayments
     */
    private function compileTypedEntries(
        Company $company,
        FranceEReportVariant $variant,
        FranceEReportContext $context,
        array $b2biInvoices,
        array $b2cTransactions,
        array $b2biPayments,
        array $b2cPayments,
        ?string $documentId,
    ): FRReportData {
        if (! $variant->isRectificative() && collect($b2biInvoices)->contains(
            static fn (B2BIInvoiceData $invoice): bool => BcMath::isNegative($invoice->amountIncludingVat, 2),
        )) {
            throw new InvalidArgumentException('Credit and rectificative B2Bi invoice mapping is not enabled for Storecove France reports.');
        }

        $b2cTransactions = $this->aggregateB2CTransactions($b2cTransactions);
        $b2cPayments = $this->aggregateB2CPayments($b2cPayments);

        usort($b2biInvoices, static fn (B2BIInvoiceData $left, B2BIInvoiceData $right): int => $left->invoiceNumber <=> $right->invoiceNumber);
        usort($b2biPayments, static fn (B2BIPaymentData $left, B2BIPaymentData $right): int => $left->invoiceNumber <=> $right->invoiceNumber);
        $declarantParty = $this->declarantParty($company);
        $this->validateB2BISuppliers($b2biInvoices, $declarantParty->publicIdentifiers[0]->id);
        $this->validateRowDates($context, $b2biInvoices, $b2cTransactions, $b2biPayments, $b2cPayments);

        $transactionReport = null;
        $paymentReport = null;

        if ($variant->isTransaction()) {
            $transactionReport = new TransactionReportData(
                period: $context->period(),
                b2biInvoices: $b2biInvoices,
                b2cTransactions: $b2cTransactions,
            );
        } else {
            $paymentReport = new PaymentReportData(
                period: $context->period(),
                b2biPayments: $b2biPayments,
                b2cPayments: $b2cPayments,
            );
        }

        return new FRReportData(
            typeCode: $variant->typeCode(),
            documentId: $documentId ?? $this->variantDocumentId(
                $context,
                $variant,
                $b2biInvoices,
                $b2cTransactions,
                $b2biPayments,
                $b2cPayments,
            ),
            issueDate: $context->issuedAt->toDateString(),
            issueTime: $context->issuedAt->format('H:i:s'),
            timeZone: $context->issuedAt->format('O'),
            declarantParty: $declarantParty,
            transactionReport: $transactionReport,
            paymentReport: $paymentReport,
        );
    }

    public function contextForVariant(
        Company $company,
        FranceEReportVariant $variant,
        string $periodEnd,
        CarbonImmutable $issuedAt,
        ?ReportingProfile $reportingProfile = null,
    ): FranceEReportContext {
        $profile = $variant->isTransaction()
            ? ($reportingProfile ?? $this->transactionProfile($company))
            : ReportingProfile::Monthly;
        $period = ReportingCalendar::currentPeriod($profile, CarbonImmutable::parse($periodEnd));

        return new FranceEReportContext(
            companyId: (int) $company->id,
            legalEntityId: (int) $company->legal_entity_id,
            periodStart: $period->start->toDateString(),
            periodEnd: $period->end->toDateString(),
            issuedAt: $issuedAt,
        );
    }

    /**
     * @param array<int, B2CTransactionData> $transactions
     * @return array<int, B2CTransactionData>
     */
    private function aggregateB2CTransactions(array $transactions): array
    {
        $groups = [];

        foreach ($transactions as $transaction) {
            $key = implode('|', [
                $transaction->date,
                $transaction->category,
                $transaction->currency,
                $transaction->vatPaymentOption ?? '',
            ]);

            $group = $groups[$key] ?? [
                'transaction' => $transaction,
                'amountExcludingVat' => '0',
                'amountIncludingVat' => '0',
                'transactionsCount' => 0,
                'rowCount' => 0,
                'countedRows' => 0,
                'subtotals' => [],
            ];
            $group['amountExcludingVat'] = BcMath::add($group['amountExcludingVat'], $transaction->amountExcludingVat, 2);
            $group['amountIncludingVat'] = BcMath::add($group['amountIncludingVat'], $transaction->amountIncludingVat, 2);
            $group['rowCount']++;

            if (! is_null($transaction->transactionsCount)) {
                $group['transactionsCount'] += $transaction->transactionsCount;
                $group['countedRows']++;
            }

            foreach ($transaction->taxSubtotals as $subtotal) {
                $percentageKey = ReportDataValidator::canonicalNumericKey($subtotal->percentage, 'taxSubtotals.percentage');
                $subtotalKey = implode('|', [
                    (string) FranceEReportTaxCategory::normalize($subtotal->category),
                    $percentageKey,
                ]);
                $current = $group['subtotals'][$subtotalKey] ?? [
                    'subtotal' => $subtotal,
                    'percentage' => $percentageKey,
                    'taxableAmount' => '0',
                    'taxAmount' => '0',
                ];
                $current['taxableAmount'] = BcMath::add($current['taxableAmount'], $subtotal->taxableAmount, 2);
                $current['taxAmount'] = BcMath::add($current['taxAmount'], $subtotal->taxAmount, 2);
                $group['subtotals'][$subtotalKey] = $current;
            }

            $groups[$key] = $group;
        }

        ksort($groups);

        return array_map(static function (array $group): B2CTransactionData {
            ksort($group['subtotals']);
            $transaction = $group['transaction'];
            $subtotals = array_map(
                static fn (array $item): TaxSubtotalData => new TaxSubtotalData(
                    percentage: $item['percentage'],
                    category: FranceEReportTaxCategory::normalize($item['subtotal']->category),
                    taxableAmount: $item['taxableAmount'],
                    taxAmount: $item['taxAmount'],
                ),
                array_values($group['subtotals']),
            );

            return new B2CTransactionData(
                date: $transaction->date,
                category: $transaction->category,
                currency: $transaction->currency,
                amountExcludingVat: $group['amountExcludingVat'],
                amountIncludingVat: $group['amountIncludingVat'],
                transactionsCount: $group['countedRows'] === $group['rowCount'] ? $group['transactionsCount'] : null,
                vatPaymentOption: $transaction->vatPaymentOption,
                taxSubtotals: $subtotals,
            );
        }, array_values($groups));
    }

    /**
     * @param array<int, B2CPaymentData> $payments
     * @return array<int, B2CPaymentData>
     */
    private function aggregateB2CPayments(array $payments): array
    {
        $groups = [];

        foreach ($payments as $payment) {
            foreach ($payment->taxSubtotal as $subtotal) {
                $percentageKey = ReportDataValidator::canonicalNumericKey($subtotal->percentage, 'taxSubtotals.percentage');
                $subtotalKey = implode('|', [
                    (string) FranceEReportTaxCategory::normalize($subtotal->category),
                    $percentageKey,
                    (string) $subtotal->country,
                    (string) $subtotal->currency,
                ]);
                $current = $groups[$payment->date][$subtotalKey] ?? [
                    'subtotal' => $subtotal,
                    'percentage' => $percentageKey,
                    'amount' => '0',
                ];
                $current['amount'] = BcMath::add($current['amount'], $subtotal->amount, 2);
                $groups[$payment->date][$subtotalKey] = $current;
            }
        }

        ksort($groups);

        return array_map(static function (array $items, string $date): B2CPaymentData {
            ksort($items);

            return new B2CPaymentData(
                date: $date,
                taxSubtotal: array_map(
                    static fn (array $item): TaxSubtotalData => new TaxSubtotalData(
                        percentage: $item['percentage'],
                        category: FranceEReportTaxCategory::normalize($item['subtotal']->category),
                        currency: $item['subtotal']->currency,
                        country: $item['subtotal']->country,
                        amount: $item['amount'],
                    ),
                    array_values($items),
                ),
            );
        }, array_values($groups), array_keys($groups));
    }

    /**
     * @param array<int, B2BIInvoiceData> $b2biInvoices
     * @param array<int, B2CTransactionData> $b2cTransactions
     * @param array<int, B2BIPaymentData> $b2biPayments
     * @param array<int, B2CPaymentData> $b2cPayments
     */
    private function validateRowDates(
        FranceEReportContext $context,
        array $b2biInvoices,
        array $b2cTransactions,
        array $b2biPayments,
        array $b2cPayments,
    ): void {
        $dates = [
            ...array_map(static fn (B2BIInvoiceData $invoice): string => $invoice->issueDate, $b2biInvoices),
            ...array_map(static fn (B2CTransactionData $transaction): string => $transaction->date, $b2cTransactions),
            ...array_map(static fn (B2BIPaymentData $payment): string => $payment->paymentDate, $b2biPayments),
            ...array_map(static fn (B2CPaymentData $payment): string => $payment->date, $b2cPayments),
        ];

        if (collect($dates)->contains(
            static fn (string $date): bool => $date < $context->periodStart || $date > $context->periodEnd,
        )) {
            throw new InvalidArgumentException('France e-report row dates must fall within the report period.');
        }
    }

    /** @param array<int, B2BIInvoiceData> $invoices */
    private function validateB2BISuppliers(array $invoices, string $declarantSiren): void
    {
        foreach ($invoices as $invoice) {
            $supplierSirens = collect($invoice->accountingSupplierParty->publicIdentifiers)
                ->filter(static fn (PublicIdentifierData $identifier): bool => $identifier->scheme === 'FR:SIRENE')
                ->map(static fn (PublicIdentifierData $identifier): string => $identifier->id)
                ->values();

            if ($supplierSirens->count() !== 1 || $supplierSirens->first() !== $declarantSiren) {
                throw new InvalidArgumentException('B2Bi supplier FR:SIRENE must match the report declarant.');
            }
        }
    }

    private function transactionProfile(Company $company): ReportingProfile
    {
        return ReportingProfile::tryFrom((string) $company->getSetting('france_reporting_schedule'))
            ?? ReportingProfile::TenDay;
    }

    /**
     * @param array<int, B2BIInvoiceData> $b2biInvoices
     * @param array<int, B2CTransactionData> $b2cTransactions
     * @param array<int, B2BIPaymentData> $b2biPayments
     * @param array<int, B2CPaymentData> $b2cPayments
     */
    private function variantDocumentId(
        FranceEReportContext $context,
        FranceEReportVariant $variant,
        array $b2biInvoices,
        array $b2cTransactions,
        array $b2biPayments,
        array $b2cPayments,
    ): string
    {
        $variantCode = match ($variant) {
            FranceEReportVariant::TransactionInitial => 'TI',
            FranceEReportVariant::TransactionRectificative => 'TR',
            FranceEReportVariant::PaymentInitial => 'PI',
            FranceEReportVariant::PaymentRectificative => 'PR',
        };

        $content = $variant->isTransaction()
            ? [
                'b2biInvoices' => array_map(static fn (B2BIInvoiceData $invoice): array => $invoice->toArray(), $b2biInvoices),
                'b2cTransactions' => array_map(static fn (B2CTransactionData $transaction): array => $transaction->toArray(), $b2cTransactions),
            ]
            : [
                'b2biPayments' => array_map(static fn (B2BIPaymentData $payment): array => $payment->toArray(), $b2biPayments),
                'b2cPayments' => array_map(static fn (B2CPaymentData $payment): array => $payment->toArray(), $b2cPayments),
            ];
        $contentHash = substr(hash('sha256', json_encode([
            'legalEntityId' => $context->legalEntityId,
            'period' => $context->period(),
            'content' => $content,
        ], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)), 0, 16);

        return 'FRF10-'.$variantCode.'-'.str_replace('-', '', $context->periodEnd).'-'.$contentHash;
    }

    private function declarantParty(Company $company): DeclarantPartyData
    {
        $identifier = $this->declarantIdentifier($company);

        return new DeclarantPartyData(
            party: new PartyData(
                companyName: $company->settings->name ?: $company->present()->name(),
            ),
            publicIdentifiers: [$identifier],
        );
    }

    private function declarantIdentifier(Company $company): PublicIdentifierData
    {
        $idNumber = preg_replace('/\D+/', '', (string) $company->getSetting('id_number')) ?: '';
        $validator = new StorecoveIdentifierValidator();

        if (strlen($idNumber) === 14) {
            if (! $validator->validFormat('FR:SIRET', $idNumber)) {
                throw new InvalidArgumentException('France e-report declarant SIRET in company id_number is invalid.');
            }

            $idNumber = substr($idNumber, 0, 9);
        }

        if (strlen($idNumber) !== 9 || ! $validator->validFormat('FR:SIRENE', $idNumber)) {
            throw new InvalidArgumentException('France e-report declarant requires a valid 9-digit SIREN in company id_number.');
        }

        return new PublicIdentifierData('FR:SIRENE', $idNumber);
    }

    private function validateContext(Company $company, FranceEReportContext $context): void
    {
        if ((int) $company->id !== $context->companyId) {
            throw new InvalidArgumentException('France e-report context companyId does not match the company.');
        }

        if ((int) $company->legal_entity_id !== $context->legalEntityId) {
            throw new InvalidArgumentException('France e-report context legalEntityId does not match the company.');
        }
    }

}
