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
use App\DataMapper\FranceEReporting\PartyData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\PublicIdentifierData;
use App\DataMapper\FranceEReporting\ReportDataValidator;
use App\DataMapper\FranceEReporting\TaxSubtotalData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use App\Utils\BcMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class FranceEReportCompiler
{
    public function compile(
        Company $company,
        int $submissionEventId,
        string $periodEnd,
        ?CarbonImmutable $issuedAt = null,
        ?string $documentId = null,
    ): FRReportData {
        $sourceEvents = $this->sourceEvents($company, $submissionEventId, $periodEnd);

        return $this->compileFromEvents($company, $submissionEventId, $periodEnd, $sourceEvents, $issuedAt, $documentId);
    }

    /**
     * @param iterable<int, TransactionEvent> $events
     */
    public function compileVariant(
        Company $company,
        FranceEReportVariant $variant,
        string $periodEnd,
        iterable $events,
        ?CarbonImmutable $issuedAt = null,
        ?string $documentId = null,
    ): FRReportData {
        $issuedAt ??= CarbonImmutable::now('Europe/Paris');

        return $this->compileVariantFromEvents(
            $company,
            $variant,
            $this->contextForVariant($company, $variant, $periodEnd, $issuedAt),
            $events,
            $documentId,
        );
    }

    /**
     * Compile exactly one Storecove F10 section for exactly one legal entity.
     *
     * @param iterable<int, TransactionEvent> $events
     */
    public function compileVariantFromEvents(
        Company $company,
        FranceEReportVariant $variant,
        FranceEReportContext $context,
        iterable $events,
        ?string $documentId = null,
    ): FRReportData {
        $this->validateContext($company, $context);

        $b2biInvoices = [];
        $b2cTransactions = [];
        $b2biPayments = [];
        $b2cPayments = [];
        $sourceEventIds = [];

        foreach ($events as $event) {
            if ((int) $event->company_id !== $context->companyId) {
                throw new InvalidArgumentException('France e-report source events must belong to the compilation company.');
            }

            if (! in_array((int) $event->event_id, $variant->sourceEventIds(), true)) {
                throw new InvalidArgumentException("Source event [{$event->event_id}] is incompatible with {$variant->value}.");
            }

            if ($event->period?->toDateString() !== $context->periodEnd) {
                throw new InvalidArgumentException('France e-report source event period does not match the compilation period.');
            }

            $sourceEventIds[] = (int) $event->id;

            $entry = $event->reporting_data?->frReportEntry;

            if (is_null($entry)) {
                throw new InvalidArgumentException("France e-report source event [{$event->id}] has no frReportEntry.");
            }

            switch ((int) $event->event_id) {
                case TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION:
                    if (! $entry->b2biInvoice instanceof B2BIInvoiceData) {
                        throw new InvalidArgumentException("France e-report source event [{$event->id}] requires b2biInvoice.");
                    }
                    $b2biInvoices[] = $entry->b2biInvoice;
                    break;
                case TransactionEvent::FR_B2C_TRANSACTION:
                    if (! $entry->b2cTransaction instanceof B2CTransactionData) {
                        throw new InvalidArgumentException("France e-report source event [{$event->id}] requires b2cTransaction.");
                    }
                    $b2cTransactions[] = $entry->b2cTransaction;
                    break;
                case TransactionEvent::FR_VAT_EXCLUDED_PAYMENT:
                    if (! $entry->b2biPayment instanceof B2BIPaymentData) {
                        throw new InvalidArgumentException("France e-report source event [{$event->id}] requires b2biPayment.");
                    }
                    $b2biPayments[] = $entry->b2biPayment;
                    break;
                case TransactionEvent::FR_B2C_PAYMENT:
                    if (! $entry->b2cPayment instanceof B2CPaymentData) {
                        throw new InvalidArgumentException("France e-report source event [{$event->id}] requires b2cPayment.");
                    }
                    $b2cPayments[] = $entry->b2cPayment;
                    break;
            }
        }

        if (collect($b2biInvoices)->contains(
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
            documentId: $documentId ?? $this->variantDocumentId($context, $variant, $sourceEventIds),
            issueDate: $context->issuedAt->toDateString(),
            issueTime: $context->issuedAt->format('H:i:s'),
            timeZone: $context->issuedAt->format('O'),
            declarantParty: $declarantParty,
            transactionReport: $transactionReport,
            paymentReport: $paymentReport,
        );
    }

    /**
     * @return Collection<int, TransactionEvent>
     */
    public function sourceEvents(Company $company, int $submissionEventId, string $periodEnd): Collection
    {
        return TransactionEvent::query()
            ->with('invoice')
            ->where('company_id', $company->id)
            ->where('period', $periodEnd)
            ->whereIn('event_id', $this->sourceEventIds($submissionEventId))
            ->whereNotNull('reporting_data')
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', TransactionEvent::FR_REPORTING_STATUS_PENDING)
                    ->orWhere('payment_status', TransactionEvent::FR_REPORTING_STATUS_FAILED);
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (TransactionEvent $event): bool => $this->isSourceEventForSubmission($event, $submissionEventId))
            ->values();
    }

    /**
     * Return all source rows for one Storecove F10 section and one legal entity.
     *
     * @return Collection<int, TransactionEvent>
     */
    public function sourceEventsForVariant(
        Company $company,
        FranceEReportVariant $variant,
        string $periodEnd,
    ): Collection {
        return TransactionEvent::query()
            ->with('invoice')
            ->where('company_id', $company->id)
            ->where('period', $periodEnd)
            ->whereIn('event_id', $variant->sourceEventIds())
            ->whereNotNull('reporting_data')
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', TransactionEvent::FR_REPORTING_STATUS_PENDING)
                    ->orWhere('payment_status', TransactionEvent::FR_REPORTING_STATUS_FAILED);
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (TransactionEvent $event): bool => $this->isSourceEventForVariant($event, $variant))
            ->values();
    }

    /**
     * @param iterable<int, TransactionEvent> $events
     */
    public function compileFromEvents(
        Company $company,
        int $submissionEventId,
        string $periodEnd,
        iterable $events,
        ?CarbonImmutable $issuedAt = null,
        ?string $documentId = null,
    ): FRReportData {
        $events = collect($events);

        return $this->compileVariant(
            $company,
            $this->variantFromEvents($submissionEventId, $events),
            $periodEnd,
            $events,
            $issuedAt,
            $documentId,
        );
    }

    /**
     * Infer a variant only for backwards-compatible queued jobs whose rows are unambiguous.
     *
     * @param iterable<int, TransactionEvent> $events
     */
    public function variantFromEvents(int $submissionEventId, iterable $events): FranceEReportVariant
    {
        $eventIds = collect($events)
            ->pluck('event_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->all();
        $containsTransaction = collect($eventIds)->contains(
            static fn (int $id): bool => in_array($id, FranceEReportVariant::TransactionInitial->sourceEventIds(), true),
        );
        $containsPayment = collect($eventIds)->contains(
            static fn (int $id): bool => in_array($id, FranceEReportVariant::PaymentInitial->sourceEventIds(), true),
        );

        if ($containsTransaction === $containsPayment) {
            throw new InvalidArgumentException('France e-report compilation requires exactly one transaction or payment source family.');
        }

        if ($containsTransaction) {
            if ($submissionEventId === TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE) {
                throw new InvalidArgumentException('Transaction RE is not enabled for the Storecove France mapper.');
            }

            return FranceEReportVariant::TransactionInitial;
        }

        return $submissionEventId === TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE
            ? FranceEReportVariant::PaymentRectificative
            : FranceEReportVariant::PaymentInitial;
    }

    public function contextForVariant(
        Company $company,
        FranceEReportVariant $variant,
        string $periodEnd,
        CarbonImmutable $issuedAt,
    ): FranceEReportContext {
        $profile = $variant->isTransaction() ? $this->transactionProfile($company) : ReportingProfile::Monthly;
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
     * @return array<int, int>
     */
    public function sourceEventIds(int $submissionEventId): array
    {
        return match ($submissionEventId) {
            TransactionEvent::FR_REPORT_SUBMISSION_B2C => [
                TransactionEvent::FR_B2C_TRANSACTION,
                TransactionEvent::FR_B2C_PAYMENT,
            ],
            TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED => [
                TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            ],
            TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE => [
                TransactionEvent::FR_B2C_TRANSACTION,
                TransactionEvent::FR_B2C_PAYMENT,
                TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            ],
            default => throw new InvalidArgumentException("Unsupported France report submission event_id [{$submissionEventId}]."),
        };
    }

    private function isSourceEventForSubmission(TransactionEvent $event, int $submissionEventId): bool
    {
        if (data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT) {
            return false;
        }

        $reportKind = data_get($event->payment_request, 'fr_report_kind', RecordFranceEReportingPayment::REPORT_KIND_INITIAL);

        if ($submissionEventId === TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE) {
            return $reportKind === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE;
        }

        if ($reportKind === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE) {
            return false;
        }

        if (in_array($event->event_id, [
            TransactionEvent::FR_B2C_PAYMENT,
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
        ], true)) {
            $invoice = $event->invoice;

            if ($event->event_id === TransactionEvent::FR_B2C_PAYMENT
                && $invoice
                && app(FranceReportEntryBuilder::class)->b2cSupplyCategory($invoice) !== 'TPS1') {
                return false;
            }

            return $invoice
                && ! $invoice->is_deleted
                && ((int) $invoice->status_id === Invoice::STATUS_PAID
                    || BcMath::lessThanOrEqual($invoice->balance ?? 0, '0', 2));
        }

        return true;
    }

    private function isSourceEventForVariant(TransactionEvent $event, FranceEReportVariant $variant): bool
    {
        if (data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT) {
            return false;
        }

        $reportKind = data_get($event->payment_request, 'fr_report_kind', RecordFranceEReportingPayment::REPORT_KIND_INITIAL);

        if ($variant === FranceEReportVariant::PaymentRectificative) {
            if ($reportKind !== RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE) {
                return false;
            }
        } elseif ($reportKind === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE) {
            return false;
        }

        if (! $variant->isTransaction()) {
            $invoice = $event->invoice;

            if ($event->event_id === TransactionEvent::FR_B2C_PAYMENT
                && $invoice
                && app(FranceReportEntryBuilder::class)->b2cSupplyCategory($invoice) !== 'TPS1') {
                return false;
            }

            return $invoice
                && ! $invoice->is_deleted
                && ((int) $invoice->status_id === Invoice::STATUS_PAID
                    || BcMath::lessThanOrEqual($invoice->balance ?? 0, '0', 2));
        }

        return true;
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

    /** @param array<int, int> $sourceEventIds */
    private function variantDocumentId(FranceEReportContext $context, FranceEReportVariant $variant, array $sourceEventIds): string
    {
        $variantCode = match ($variant) {
            FranceEReportVariant::TransactionInitial => 'TI',
            FranceEReportVariant::PaymentInitial => 'PI',
            FranceEReportVariant::PaymentRectificative => 'PR',
        };

        sort($sourceEventIds);
        $sourceHash = substr(hash('sha256', $context->legalEntityId.'|'.implode(',', $sourceEventIds)), 0, 8);

        return 'FRF10-'.$variantCode.'-'.str_replace('-', '', $context->periodEnd).'-'.$context->issuedAt->format('His').'-'.$sourceHash;
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
