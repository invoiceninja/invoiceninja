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

namespace App\Services\Report;

use App\DataMapper\TransactionEventMetadata;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\Payment\PaymentApplicationDateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReconcilePaymentApplicationTransactionEventsRunner
{
    /** @var array<int, true> */
    private array $claimedLegacyEventIds = [];

    public function __construct(
        private InvoiceTransactionEventEntryCash $writer,
        private PaymentApplicationDateResolver $resolver,
    ) {}

    /**
     * @param  list<int>|null  $payment_ids
     * @return array{summary: array<string, mixed>, problems: list<array<string, mixed>>, repairs: list<array<string, mixed>>}
     */
    public function runForCompany(
        Company $company,
        bool $remediate_v2,
        bool $remediate_legacy_application_dates,
        ?array $payment_ids = null,
    ): array {
        $this->claimedLegacyEventIds = [];

        $timezone = $company->timezone()?->name ?: config('app.timezone');

        $cash_reports = [
            'profit_loss' => 'Profit & Loss (payments / cash basis)',
            'tax_summary' => 'Tax Summary (cash activity section)',
            'tax_period' => 'Tax Period report (cash accounting)',
        ];

        if ((bool) $company->getSetting('france_reporting_enabled')) {
            $cash_reports['france_e_reporting'] = 'France e-reporting (payment projections from transaction events)';
        }

        $paymentable_query = Paymentable::query()
            ->with([
                'payment' => fn ($query) => $query->withTrashed(),
            ])
            ->where('paymentable_type', 'invoices')
            ->whereNull('deleted_at')
            ->whereHas('payment', fn ($query) => $query
                ->withTrashed()
                ->where('company_id', $company->id)
                ->where('is_deleted', false));

        if ($payment_ids !== null) {
            $paymentable_query->whereIn('payment_id', $payment_ids);
        }

        $problems = [];
        $repairs = [];
        $summary = [
            'company_id' => $company->id,
            'scanned' => 0,
            'problem_count' => 0,
            'by_issue' => [],
            'remediated' => 0,
            'legacy_dates_synced' => 0,
            'fix_failed' => 0,
            'cash_reports_using_transaction_events' => $cash_reports,
        ];

        foreach ($paymentable_query->orderBy('id')->lazyById(200) as $paymentable) {
            $summary['scanned']++;
            $intended = $this->resolver->resolve($paymentable, $timezone);

            if ($intended === null) {
                continue;
            }

            $invoice_id = (int) $paymentable->paymentable_id;
            $payment_id = (int) $paymentable->payment_id;
            $payment_date = $this->normalizeDate($paymentable->payment?->date); //@phpstan-ignore-line
            $source = $this->writer->findSourceEvent($invoice_id, (int) $paymentable->id);

            if ($source) {
                $this->processV2Source(
                    $company,
                    $paymentable,
                    $invoice_id,
                    $payment_id,
                    $intended,
                    $payment_date,
                    $source,
                    $cash_reports,
                    $remediate_v2,
                    $problems,
                    $repairs,
                    $summary,
                );

                continue;
            }

            $this->processWithoutV2Source(
                $company,
                $paymentable,
                $invoice_id,
                $payment_id,
                $intended,
                $payment_date,
                $timezone,
                $cash_reports,
                $remediate_v2,
                $remediate_legacy_application_dates,
                $problems,
                $repairs,
                $summary,
            );
        }

        $summary['problem_count'] = count($problems);

        return [
            'summary' => $summary,
            'problems' => $problems,
            'repairs' => $repairs,
        ];
    }

    /**
     * When a legacy PAYMENT_CASH row has payment_id = 0, bind it to the single provable payment application.
     */
    public function attemptLegacyPaymentIdBackfillForEvent(Company $company, TransactionEvent $event): bool
    {
        if ((int) $event->company_id !== (int) $company->id) {
            return false;
        }

        if ((int) $event->payment_id !== 0
            || (int) $event->event_id !== TransactionEvent::PAYMENT_CASH
            || data_get($event->payment_request, 'schema_version')) {
            return false;
        }

        $timezone = $company->timezone()?->name ?: config('app.timezone');
        $period = $event->period?->toDateString();

        if ($period === null) {
            return false;
        }

        $paymentables = Paymentable::query()
            ->with(['payment' => fn ($query) => $query->withTrashed()])
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $event->invoice_id)
            ->whereNull('deleted_at')
            ->whereHas('payment', fn ($query) => $query
                ->withTrashed()
                ->where('company_id', $company->id)
                ->where('is_deleted', false))
            ->orderBy('id')
            ->get()
            ->filter(function (Paymentable $paymentable) use ($company, $event, $timezone): bool {
                if ($this->writer->findSourceEvent((int) $event->invoice_id, (int) $paymentable->id)) {
                    return false;
                }

                $intended = $this->resolver->resolve($paymentable, $timezone);

                if ($intended === null) {
                    return false;
                }

                $legacy_match = $this->identifyLegacyInIntendedMonth(
                    $company->id,
                    (int) $event->invoice_id,
                    $intended,
                    $timezone,
                    $paymentable,
                );

                return ! $legacy_match['ambiguous']
                    && $legacy_match['event']
                    && (int) $legacy_match['event']->id === (int) $event->id;
            });

        if ($paymentables->count() !== 1) {
            return false;
        }

        /** @var Paymentable $paymentable */
        $paymentable = $paymentables->first();
        $legacy_match = $this->identifyLegacyInIntendedMonth(
            $company->id,
            (int) $event->invoice_id,
            (string) $this->resolver->resolve($paymentable, $timezone),
            $timezone,
            $paymentable,
        );

        $assessment = $this->assessLegacyPaymentIdBackfill(
            $paymentable,
            $event,
            $legacy_match['history_index'],
        );

        if (! $assessment['eligible']) {
            return false;
        }

        $payment_id = (int) $paymentable->payment_id;

        return DB::transaction(function () use ($company, $event, $paymentable, $payment_id): bool {
            $locked = TransactionEvent::query()
                ->where('company_id', $company->id)
                ->whereKey($event->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || (int) $locked->payment_id !== 0) {
                return false;
            }

            $payment = $paymentable->payment;

            if (! $payment || $payment->is_deleted || (int) $payment->id !== $payment_id) { // @phpstan-ignore-line
                return false;
            }

            $reassessment = $this->assessLegacyPaymentIdBackfill(
                $paymentable,
                $locked,
                $this->resolveHistoryIndexForLegacyEvent($locked, $paymentable),
            );

            if (! $reassessment['eligible']) {
                return false;
            }

            $locked->payment_id = $payment_id;
            $locked->payment_status = $payment->status_id;

            return $locked->save();
        }, attempts: 3);
    }

    /**
     * @return array{eligible: bool, skip_reason: string|null}
     */
    private function assessLegacyPaymentIdBackfill(
        Paymentable $paymentable,
        TransactionEvent $legacy_event,
        ?int $history_index,
    ): array {
        if (data_get($legacy_event->payment_request, 'schema_version')) {
            return ['eligible' => false, 'skip_reason' => 'not_legacy_event'];
        }

        if ((int) $legacy_event->event_id !== TransactionEvent::PAYMENT_CASH) {
            return ['eligible' => false, 'skip_reason' => 'not_payment_cash'];
        }

        $payment_id = (int) $paymentable->payment_id;

        if ($payment_id <= 0) {
            return ['eligible' => false, 'skip_reason' => 'missing_payment_id_on_paymentable'];
        }

        $payment = $paymentable->payment;

        if (! $payment || $payment->is_deleted) { // @phpstan-ignore-line
            return ['eligible' => false, 'skip_reason' => 'payment_missing_or_deleted'];
        }

        if ((int) $legacy_event->payment_id !== 0 && (int) $legacy_event->payment_id !== $payment_id) {
            return ['eligible' => false, 'skip_reason' => 'legacy_payment_id_mismatch'];
        }

        if (abs((float) $legacy_event->payment_applied - (float) $paymentable->amount) >= 0.01) {
            return ['eligible' => false, 'skip_reason' => 'amount_mismatch'];
        }

        $history = data_get($legacy_event->metadata->toArray(), 'tax_report.payment_history');

        if (is_array($history) && count($history) > 1) {
            if ($history_index === null) {
                return ['eligible' => false, 'skip_reason' => 'aggregated_legacy_event'];
            }

            $entry = $this->normalizeHistoryEntry($history[$history_index]);

            if ($entry === null) {
                return ['eligible' => false, 'skip_reason' => 'missing_payment_history'];
            }

            if (isset($entry['paymentable_id'])
                && (int) $entry['paymentable_id'] > 0
                && (int) $entry['paymentable_id'] !== (int) $paymentable->id) {
                return ['eligible' => false, 'skip_reason' => 'payment_history_paymentable_id_mismatch'];
            }

            $entry_amount = $entry['amount'] ?? null;

            if ($entry_amount !== null && abs((float) $entry_amount - (float) $paymentable->amount) >= 0.01) {
                return ['eligible' => false, 'skip_reason' => 'payment_history_amount_mismatch'];
            }

            $number = (string) $payment->number;

            if ($number !== ''
                && isset($entry['number'])
                && (string) $entry['number'] !== $number) {
                return ['eligible' => false, 'skip_reason' => 'payment_history_number_mismatch'];
            }
        }

        $resolved_index = $this->resolveHistoryIndexForLegacyEvent($legacy_event, $paymentable);

        if ($resolved_index === null) {
            return ['eligible' => false, 'skip_reason' => 'payment_history_entry_not_uniquely_identified'];
        }

        if ($history_index !== null && $resolved_index !== $history_index) {
            return ['eligible' => false, 'skip_reason' => 'payment_history_index_mismatch'];
        }

        return ['eligible' => true, 'skip_reason' => null];
    }

    /**
     * @param  array<string, string>  $cash_reports
     * @param  list<array<string, mixed>>  $problems
     * @param  list<array<string, mixed>>  $repairs
     * @param  array<string, mixed>  $summary
     */
    private function processV2Source(
        Company $company,
        Paymentable $paymentable,
        int $invoice_id,
        int $payment_id,
        string $intended,
        ?string $payment_date,
        TransactionEvent $source,
        array $cash_reports,
        bool $remediate_v2,
        array &$problems,
        array &$repairs,
        array &$summary,
    ): void {
        $ledger = $this->ledgerApplicationDate($source);

        if ($ledger === $intended) {
            return;
        }

        $cross_month = substr($ledger, 0, 7) !== substr($intended, 0, 7);

        if ($remediate_v2 && $this->canAutoRemediateV2Drift($source, $paymentable)) {
            $this->writer->reconcileApplicationDateChange(
                $invoice_id,
                $payment_id,
                $ledger,
                $intended,
                [(int) $paymentable->id],
            );

            $source->refresh();
            $ledger_after = $this->ledgerApplicationDate($source);

            if ($ledger_after === $intended) {
                $summary['remediated']++;
                $repairs[] = $this->repairRow(
                    $paymentable,
                    $payment_id,
                    $invoice_id,
                    'v2_date_drift',
                    $ledger,
                    $intended,
                    (int) $source->id,
                );

                return;
            }

            $summary['fix_failed']++;
            $this->recordProblem(
                $problems,
                $summary,
                'v2_date_drift',
                $paymentable,
                $payment_id,
                $invoice_id,
                $intended,
                $payment_date,
                $source->id,
                $ledger_after,
                $this->reportImpact($cash_reports, 'v2_date_drift', $cross_month),
                [
                    'outcome' => 'fix_failed',
                    'skip_reason' => 'ledger_not_aligned_after_reconcile',
                ],
            );

            return;
        }

        $this->recordProblem(
            $problems,
            $summary,
            'v2_date_drift',
            $paymentable,
            $payment_id,
            $invoice_id,
            $intended,
            $payment_date,
            $source->id,
            $ledger,
            $this->reportImpact($cash_reports, 'v2_date_drift', $cross_month),
            [
                'outcome' => 'manual_review_required',
                'auto_remediable' => $this->canAutoRemediateV2Drift($source, $paymentable),
            ],
        );
    }

    /**
     * @param  array<string, string>  $cash_reports
     * @param  list<array<string, mixed>>  $problems
     * @param  list<array<string, mixed>>  $repairs
     * @param  array<string, mixed>  $summary
     */
    private function processWithoutV2Source(
        Company $company,
        Paymentable $paymentable,
        int $invoice_id,
        int $payment_id,
        string $intended,
        ?string $payment_date,
        string $timezone,
        array $cash_reports,
        bool $remediate_v2,
        bool $remediate_legacy_application_dates,
        array &$problems,
        array &$repairs,
        array &$summary,
    ): void {
        $legacy_in_intended = $this->identifyLegacyInIntendedMonth(
            $company->id,
            $invoice_id,
            $intended,
            $timezone,
            $paymentable,
        );

        if ($legacy_in_intended['ambiguous']) {
            $this->recordProblem(
                $problems,
                $summary,
                'ambiguous_legacy_cash_events',
                $paymentable,
                $payment_id,
                $invoice_id,
                $intended,
                $payment_date,
                null,
                null,
                $this->reportImpact($cash_reports, 'ambiguous_legacy_cash_events'),
                [
                    'outcome' => 'manual_review_required',
                    'candidate_count' => $legacy_in_intended['candidate_count'],
                ],
            );

            return;
        }

        $legacy_event = $legacy_in_intended['event'];
        $history_index = $legacy_in_intended['history_index'];

        if (! $legacy_event) {
            $elsewhere = $this->legacyRepresentationsElsewhere(
                $company->id,
                $invoice_id,
                $intended,
                $timezone,
                $paymentable,
            );

            if ($elsewhere->isNotEmpty()) {
                $this->recordProblem(
                    $problems,
                    $summary,
                    'cash_represented_in_other_period',
                    $paymentable,
                    $payment_id,
                    $invoice_id,
                    $intended,
                    $payment_date,
                    (int) $elsewhere->first()->id,
                    $this->legacyHistoryDate($elsewhere->first(), $paymentable) ?? $elsewhere->first()->period?->toDateString(),
                    $this->reportImpact($cash_reports, 'cash_represented_in_other_period', true),
                    [
                        'outcome' => 'manual_review_required',
                        'other_period_event_ids' => $elsewhere->pluck('id')->values()->all(),
                    ],
                );

                return;
            }

            $unresolved = $this->unresolvedLegacyCashElsewhere(
                $company->id,
                $invoice_id,
                $intended,
                $timezone,
                $paymentable,
            );

            if ($unresolved->isNotEmpty()) {
                $this->recordProblem(
                    $problems,
                    $summary,
                    'unresolved_historical_cash',
                    $paymentable,
                    $payment_id,
                    $invoice_id,
                    $intended,
                    $payment_date,
                    (int) $unresolved->first()->id,
                    $unresolved->first()->period?->toDateString(),
                    $this->reportImpact($cash_reports, 'unresolved_historical_cash', true),
                    [
                        'outcome' => 'manual_review_required',
                        'other_period_event_ids' => $unresolved->pluck('id')->values()->all(),
                    ],
                );

                return;
            }

            if ($remediate_v2 && $this->canAutoBackfillV2Source($company, $invoice_id, $intended, $timezone, $paymentable)) {
                $invoice = Invoice::withTrashed()
                    ->where('company_id', $company->id)
                    ->find($invoice_id);

                if ($invoice && ! $invoice->client->is_deleted) {
                    $created = $this->writer->runForPaymentable($invoice, $paymentable);
                    $source_after = $this->writer->findSourceEvent($invoice_id, (int) $paymentable->id);

                    if ($created && $source_after && $this->ledgerApplicationDate($source_after) === $intended) {
                        $summary['remediated']++;
                        $repairs[] = $this->repairRow(
                            $paymentable,
                            $payment_id,
                            $invoice_id,
                            'missing_cash_ledger',
                            null,
                            $intended,
                            (int) $source_after->id,
                        );

                        return;
                    }
                }

                $summary['fix_failed']++;
            }

            $this->recordProblem(
                $problems,
                $summary,
                'missing_cash_ledger',
                $paymentable,
                $payment_id,
                $invoice_id,
                $intended,
                $payment_date,
                null,
                null,
                $this->reportImpact($cash_reports, 'missing_cash_ledger'),
                [
                    'outcome' => $remediate_v2 ? 'fix_failed' : 'detected',
                    'auto_remediable' => $this->canAutoBackfillV2Source($company, $invoice_id, $intended, $timezone, $paymentable),
                ],
            );

            return;
        }

        $legacy_payment_date = $this->legacyHistoryDateAtIndex($legacy_event, $history_index);

        if ($legacy_payment_date === null || $legacy_payment_date === $intended) {
            return;
        }

        $cross_month = substr($legacy_payment_date, 0, 7) !== substr($intended, 0, 7);
        $candidates = $this->legacyCandidates($company->id, $invoice_id, $intended, $timezone);
        $assessment = $this->assessLegacyDateSync(
            $paymentable,
            $legacy_event,
            $intended,
            $payment_date,
            $legacy_payment_date,
            $candidates,
            $history_index,
        );

        if ($assessment['eligible'] && $remediate_legacy_application_dates && $history_index !== null) {
            $synced = DB::transaction(function () use (
                $company,
                $legacy_event,
                $intended,
                $history_index,
                $paymentable,
                $payment_date,
                $candidates,
            ): bool {
                $locked = TransactionEvent::query()
                    ->where('company_id', $company->id)
                    ->whereKey($legacy_event->id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    return false;
                }

                if (isset($this->claimedLegacyEventIds[(int) $locked->id])) {
                    return false;
                }

                $locked_legacy_date = $this->legacyHistoryDateAtIndex($locked, $history_index) ?? '';

                $reassessment = $this->assessLegacyDateSync(
                    $paymentable,
                    $locked,
                    $intended,
                    $payment_date,
                    $locked_legacy_date,
                    $candidates,
                    $history_index,
                );

                if (! $reassessment['eligible']) {
                    return false;
                }

                return $this->syncLegacyPaymentHistoryDate($locked, $intended, $history_index);
            });

            if ($synced) {
                $this->claimedLegacyEventIds[(int) $legacy_event->id] = true;
                $legacy_event->refresh();
                $after = $this->legacyHistoryDateAtIndex($legacy_event, $history_index);

                if ($after === $intended) {
                    $summary['legacy_dates_synced']++;
                    $repairs[] = $this->repairRow(
                        $paymentable,
                        $payment_id,
                        $invoice_id,
                        'legacy_application_date_drift',
                        $legacy_payment_date,
                        $intended,
                        (int) $legacy_event->id,
                    );

                    return;
                }

                $summary['fix_failed']++;
            } elseif ($remediate_legacy_application_dates) {
                $summary['fix_failed']++;
            }
        }

        $this->recordProblem(
            $problems,
            $summary,
            'legacy_application_date_drift',
            $paymentable,
            $payment_id,
            $invoice_id,
            $intended,
            $payment_date,
            $legacy_event->id,
            $legacy_payment_date,
            $this->reportImpact($cash_reports, 'legacy_application_date_drift', $cross_month),
            [
                'outcome' => $assessment['eligible'] && ! $remediate_legacy_application_dates
                    ? 'detected'
                    : ($assessment['eligible'] ? 'fix_failed' : 'manual_review_required'),
                'legacy_remediation' => $assessment['eligible'] ? 'eligible' : 'skipped',
                'skip_reason' => $assessment['skip_reason'],
                'payment_history_index' => $history_index,
            ],
        );
    }

    private function canAutoRemediateV2Drift(TransactionEvent $source, Paymentable $paymentable): bool
    {
        return (int) data_get($source->payment_request, 'source_paymentable_id') === (int) $paymentable->id
            && (int) $source->payment_id === (int) $paymentable->payment_id;
    }

    private function canAutoBackfillV2Source(
        Company $company,
        int $invoice_id,
        string $intended,
        string $timezone,
        Paymentable $paymentable,
    ): bool {
        if ($this->legacyRepresentationsElsewhere($company->id, $invoice_id, $intended, $timezone, $paymentable)->isNotEmpty()) {
            return false;
        }

        if ($this->unresolvedLegacyCashElsewhere($company->id, $invoice_id, $intended, $timezone, $paymentable)->isNotEmpty()) {
            return false;
        }

        return $this->writer->findSourceEvent($invoice_id, (int) $paymentable->id) === null;
    }

    /**
     * @return array{event: TransactionEvent|null, ambiguous: bool, candidate_count: int, history_index: int|null}
     */
    private function identifyLegacyInIntendedMonth(
        int $company_id,
        int $invoice_id,
        string $intended,
        string $timezone,
        Paymentable $paymentable,
    ): array {
        $candidates = $this->legacyCandidates($company_id, $invoice_id, $intended, $timezone);
        $candidate_count = $candidates->count();

        if ($candidates->isEmpty()) {
            return ['event' => null, 'ambiguous' => false, 'candidate_count' => 0, 'history_index' => null];
        }

        $payment_id = (int) $paymentable->payment_id;

        $by_payment_id = $candidates->filter(
            fn (TransactionEvent $event): bool => $payment_id > 0 && (int) $event->payment_id === $payment_id,
        );

        if ($by_payment_id->count() > 1) {
            return ['event' => null, 'ambiguous' => true, 'candidate_count' => $candidate_count, 'history_index' => null];
        }

        if ($by_payment_id->count() === 1) {
            $event = $by_payment_id->first();
            $index = $this->resolveHistoryIndexForLegacyEvent($event, $paymentable);

            if ($index === null) {
                return ['event' => null, 'ambiguous' => true, 'candidate_count' => $candidate_count, 'history_index' => null];
            }

            return ['event' => $event, 'ambiguous' => false, 'candidate_count' => $candidate_count, 'history_index' => $index];
        }

        $by_history = $candidates->filter(
            fn (TransactionEvent $event): bool => $this->findPaymentHistoryIndex($event, $paymentable) !== null,
        );

        if ($by_history->count() > 1) {
            return ['event' => null, 'ambiguous' => true, 'candidate_count' => $candidate_count, 'history_index' => null];
        }

        if ($by_history->count() === 1) {
            $event = $by_history->first();
            $index = $this->resolveHistoryIndexForLegacyEvent($event, $paymentable);

            if ($index === null) {
                return ['event' => null, 'ambiguous' => true, 'candidate_count' => $candidate_count, 'history_index' => null];
            }

            return [
                'event' => $event,
                'ambiguous' => false,
                'candidate_count' => $candidate_count,
                'history_index' => $index,
            ];
        }

        $amount = (float) $paymentable->amount;
        $amount_matches = $candidates->filter(
            fn (TransactionEvent $event): bool => abs((float) $event->payment_applied - $amount) < 0.01,
        );

        if ($amount_matches->count() === 1) {
            $event = $amount_matches->first();

            if ((int) $event->payment_id !== 0 && (int) $event->payment_id !== $payment_id) {
                return ['event' => null, 'ambiguous' => true, 'candidate_count' => $candidate_count, 'history_index' => null];
            }

            $index = $this->resolveHistoryIndexForLegacyEvent($event, $paymentable);

            if ($index === null) {
                return ['event' => null, 'ambiguous' => true, 'candidate_count' => $candidate_count, 'history_index' => null];
            }

            return ['event' => $event, 'ambiguous' => false, 'candidate_count' => $candidate_count, 'history_index' => $index];
        }

        if ($candidate_count === 1) {
            $event = $candidates->first();
            $index = $this->resolveHistoryIndexForLegacyEvent($event, $paymentable);

            if ($index !== null) {
                return ['event' => $event, 'ambiguous' => false, 'candidate_count' => 1, 'history_index' => $index];
            }

            return ['event' => null, 'ambiguous' => true, 'candidate_count' => 1, 'history_index' => null];
        }

        return ['event' => null, 'ambiguous' => true, 'candidate_count' => $candidate_count, 'history_index' => null];
    }

    /**
     * Legacy cash rows on this invoice (any period) tied to this payment application.
     */
    private function legacyRepresentationsElsewhere(
        int $company_id,
        int $invoice_id,
        string $intended,
        string $timezone,
        Paymentable $paymentable,
    ): Collection {
        $intended_period = CarbonImmutable::parse($intended, $timezone)->endOfMonth()->toDateString();

        return $this->legacyEventsForInvoice($company_id, $invoice_id)
            ->filter(function (TransactionEvent $event) use ($intended_period, $paymentable): bool {
                if ($event->period?->toDateString() === $intended_period) {
                    return false;
                }

                return $this->legacyEventMatchesPaymentApplication($event, $paymentable);
            })
            ->values();
    }

    /**
     * Other-period legacy rows that may represent this payment but cannot be tied to this application.
     */
    private function unresolvedLegacyCashElsewhere(
        int $company_id,
        int $invoice_id,
        string $intended,
        string $timezone,
        Paymentable $paymentable,
    ): Collection {
        $intended_period = CarbonImmutable::parse($intended, $timezone)->endOfMonth()->toDateString();
        $payment_id = (int) $paymentable->payment_id;
        $amount = (float) $paymentable->amount;

        return $this->legacyEventsForInvoice($company_id, $invoice_id)
            ->filter(function (TransactionEvent $event) use ($intended_period, $paymentable, $payment_id, $amount): bool {
                if ($event->period?->toDateString() === $intended_period) {
                    return false;
                }

                if ($this->legacyEventMatchesPaymentApplication($event, $paymentable)) {
                    return false;
                }

                if (abs((float) $event->payment_applied - $amount) >= 0.01) {
                    return false;
                }

                if ($payment_id > 0 && (int) $event->payment_id === $payment_id) {
                    return true;
                }

                if ((int) $event->payment_id !== 0) {
                    return false;
                }

                return $this->legacyEventHasWeakPaymentIdentifiers($event);
            })
            ->values();
    }

    private function legacyEventHasWeakPaymentIdentifiers(TransactionEvent $event): bool
    {
        $history = data_get($event->metadata->toArray(), 'tax_report.payment_history');

        if (! is_array($history) || $history === []) {
            return true;
        }

        foreach ($history as $entry) {
            $entry = $this->normalizeHistoryEntry($entry);

            if ($entry === null) {
                continue;
            }

            if (isset($entry['paymentable_id']) && (int) $entry['paymentable_id'] > 0) {
                return false;
            }

            if (isset($entry['number']) && (string) $entry['number'] !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeHistoryEntry(mixed $entry): ?array
    {
        if (is_array($entry)) {
            return $entry;
        }

        if (is_object($entry)) {
            $normalized = json_decode(json_encode($entry), true);

            return is_array($normalized) ? $normalized : null;
        }

        return null;
    }

    private function legacyEventsForInvoice(int $company_id, int $invoice_id): Collection
    {
        return TransactionEvent::query()
            ->where('company_id', $company_id)
            ->where('invoice_id', $invoice_id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->get()
            ->filter(fn (TransactionEvent $event): bool => ! data_get($event->payment_request, 'schema_version'));
    }

    private function legacyEventMatchesPaymentApplication(TransactionEvent $event, Paymentable $paymentable): bool
    {
        $payment_id = (int) $paymentable->payment_id;

        if ($payment_id > 0 && (int) $event->payment_id === $payment_id) {
            return true;
        }

        return $this->findPaymentHistoryIndex($event, $paymentable) !== null;
    }

    private function resolveHistoryIndexForLegacyEvent(TransactionEvent $event, Paymentable $paymentable): ?int
    {
        $index = $this->findPaymentHistoryIndex($event, $paymentable);

        if ($index !== null) {
            return $index;
        }

        $payment_id = (int) $paymentable->payment_id;

        if ($payment_id <= 0 || (int) $event->payment_id !== $payment_id) {
            return null;
        }

        $history = data_get($event->metadata, 'tax_report.payment_history');

        if (! is_array($history)) {
            $history = data_get($event->metadata->toArray(), 'tax_report.payment_history');
        }

        if (! is_array($history) || count($history) !== 1) {
            return null;
        }

        $entry = $this->normalizeHistoryEntry($history[0]);

        if ($entry === null) {
            return null;
        }

        if (isset($entry['paymentable_id'])
            && (int) $entry['paymentable_id'] > 0
            && (int) $entry['paymentable_id'] !== (int) $paymentable->id) {
            return null;
        }

        $entry_amount = $entry['amount'] ?? null;
        $reference_amount = $entry_amount !== null
            ? (float) $entry_amount
            : (float) $event->payment_applied;

        if (abs($reference_amount - (float) $paymentable->amount) >= 0.01) {
            return null;
        }

        return 0;
    }

    private function findPaymentHistoryIndex(TransactionEvent $event, Paymentable $paymentable): ?int
    {
        $history = data_get($event->metadata, 'tax_report.payment_history');

        if (! is_array($history)) {
            $history = data_get($event->metadata->toArray(), 'tax_report.payment_history');
        }

        if (! is_array($history)) {
            return null;
        }

        $paymentable_id = (int) $paymentable->id;
        $paymentable->loadMissing('payment');
        $number = (string) $paymentable->payment?->number; //@phpstan-ignore-line
        $amount = (float) $paymentable->amount;

        foreach ($history as $index => $entry) {
            $entry = $this->normalizeHistoryEntry($entry);

            if ($entry === null) {
                continue;
            }

            if (isset($entry['paymentable_id'])
                && (int) $entry['paymentable_id'] > 0
                && (int) $entry['paymentable_id'] === $paymentable_id) {
                return (int) $index;
            }
        }

        $number_amount_matches = [];

        foreach ($history as $index => $entry) {
            $entry = $this->normalizeHistoryEntry($entry);

            if ($entry === null) {
                continue;
            }

            if (isset($entry['paymentable_id'])
                && (int) $entry['paymentable_id'] > 0
                && (int) $entry['paymentable_id'] !== $paymentable_id) {
                continue;
            }

            if ($number !== ''
                && (string) ($entry['number'] ?? '') === $number
                && abs((float) ($entry['amount'] ?? 0) - $amount) < 0.01) {
                $number_amount_matches[] = (int) $index;
            }
        }

        if (count($number_amount_matches) === 1) {
            return $number_amount_matches[0];
        }

        return null;
    }

    private function legacyHistoryDate(TransactionEvent $event, Paymentable $paymentable): ?string
    {
        $index = $this->findPaymentHistoryIndex($event, $paymentable);

        return $this->legacyHistoryDateAtIndex($event, $index ?? 0);
    }

    private function legacyHistoryDateAtIndex(TransactionEvent $event, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $date = data_get($event->metadata, 'tax_report.payment_history.'.$index.'.date');

        return $date !== null && $date !== '' ? (string) $date : null;
    }

    private function normalizeDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if ($date === null || $date === '') {
            return null;
        }

        return (string) $date;
    }

    private function ledgerApplicationDate(TransactionEvent $source): string
    {
        $ledger = (string) data_get($source->payment_request, 'effective_date', '');

        $latest_apply = TransactionEvent::query()
            ->where('invoice_id', $source->invoice_id)
            ->where('payment_id', $source->payment_id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->get()
            ->filter(fn (TransactionEvent $event): bool => (int) data_get($event->payment_request, 'source_event_id') === (int) $source->id
                && data_get($event->payment_request, 'tax_correction_kind') === 'payment_application_date'
                && data_get($event->payment_request, 'direction') === 'apply')
            ->sortByDesc('id')
            ->first();

        if ($latest_apply) {
            $ledger = (string) data_get($latest_apply->payment_request, 'effective_date', $ledger);
        }

        if ($ledger === '') {
            $ledger = (string) data_get($latest_apply?->metadata, 'tax_report.payment_history.0.date', $source->period?->toDateString() ?? '');
        }

        return $ledger;
    }

    private function legacyCandidates(
        int $company_id,
        int $invoice_id,
        string $intended,
        string $timezone,
    ): Collection {
        $period_end = CarbonImmutable::parse($intended, $timezone)->endOfMonth()->toDateString();

        return TransactionEvent::query()
            ->where('company_id', $company_id)
            ->where('invoice_id', $invoice_id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->whereDate('period', $period_end)
            ->get()
            ->filter(fn (TransactionEvent $event): bool => ! data_get($event->payment_request, 'schema_version'));
    }

    /**
     * @return array{eligible: bool, skip_reason: string|null}
     */
    private function assessLegacyDateSync(
        Paymentable $paymentable,
        TransactionEvent $legacy_event,
        string $intended,
        ?string $payment_date,
        string $legacy_payment_date,
        Collection $candidates,
        ?int $history_index,
    ): array {
        if ($history_index === null) {
            return ['eligible' => false, 'skip_reason' => 'payment_history_entry_not_identified'];
        }

        if (isset($this->claimedLegacyEventIds[(int) $legacy_event->id])) {
            return ['eligible' => false, 'skip_reason' => 'legacy_event_already_claimed'];
        }

        $payment_id = (int) $paymentable->payment_id;

        if ((int) $legacy_event->payment_id !== 0 && (int) $legacy_event->payment_id !== $payment_id) {
            return ['eligible' => false, 'skip_reason' => 'legacy_payment_id_mismatch'];
        }

        if ($candidates->count() > 1) {
            $identified = $candidates->filter(
                fn (TransactionEvent $event): bool => $this->legacyEventMatchesPaymentApplication($event, $paymentable),
            );

            if ($identified->count() !== 1 || (int) $identified->first()->id !== (int) $legacy_event->id) {
                return ['eligible' => false, 'skip_reason' => 'ambiguous_legacy_event_in_period'];
            }
        }

        if ($payment_date === null || $payment_date === '' || $payment_date !== $intended) {
            return ['eligible' => false, 'skip_reason' => 'payment_date_differs_from_application'];
        }

        if (substr($legacy_payment_date, 0, 7) !== substr($intended, 0, 7)) {
            return ['eligible' => false, 'skip_reason' => 'cross_month'];
        }

        if (abs((float) $legacy_event->payment_applied - (float) $paymentable->amount) >= 0.01) {
            return ['eligible' => false, 'skip_reason' => 'amount_mismatch'];
        }

        $history = data_get($legacy_event->metadata->toArray(), 'tax_report.payment_history');

        if (! is_array($history) || ! isset($history[$history_index])) {
            return ['eligible' => false, 'skip_reason' => 'missing_payment_history'];
        }

        $entry = $this->normalizeHistoryEntry($history[$history_index]);

        if ($entry === null) {
            return ['eligible' => false, 'skip_reason' => 'missing_payment_history'];
        }

        $history_amount = $entry['amount'] ?? null;

        if ($history_amount !== null && abs((float) $history_amount - (float) $paymentable->amount) >= 0.01) {
            return ['eligible' => false, 'skip_reason' => 'payment_history_amount_mismatch'];
        }

        if (isset($entry['paymentable_id'])
            && (int) $entry['paymentable_id'] > 0
            && (int) $entry['paymentable_id'] !== (int) $paymentable->id) {
            return ['eligible' => false, 'skip_reason' => 'payment_history_paymentable_id_mismatch'];
        }

        $number = (string) $paymentable->payment?->number; //@phpstan-ignore-line

        if ($number !== '' && isset($entry['number']) && (string) $entry['number'] !== $number) {
            return ['eligible' => false, 'skip_reason' => 'payment_history_number_mismatch'];
        }

        $resolved_index = $this->resolveHistoryIndexForLegacyEvent($legacy_event, $paymentable);

        if ($resolved_index === null || $resolved_index !== $history_index) {
            return ['eligible' => false, 'skip_reason' => 'payment_history_entry_not_uniquely_identified'];
        }

        return ['eligible' => true, 'skip_reason' => null];
    }

    private function syncLegacyPaymentHistoryDate(TransactionEvent $event, string $intended, int $history_index): bool
    {
        if (data_get($event->payment_request, 'schema_version')) {
            return false;
        }

        $metadata_array = $event->metadata->toArray();
        $history = data_get($metadata_array, 'tax_report.payment_history');

        if (! is_array($history) || ! isset($history[$history_index])) {
            return false;
        }

        $metadata_array['tax_report']['payment_history'][$history_index]['date'] = $intended;
        $event->metadata = new TransactionEventMetadata($metadata_array);

        return $event->save();
    }

    /**
     * @param  array<string, string>  $cash_reports
     * @return array{reports: list<string>, labels: list<string>, detail: string, severity: string}
     */
    private function reportImpact(array $cash_reports, string $issue, bool $cross_month = false): array
    {
        $all_cash = array_keys($cash_reports);

        return match ($issue) {
            'v2_date_drift' => [
                'reports' => $all_cash,
                'labels' => array_values($cash_reports),
                'detail' => 'CashTaxEventProjector uses v2 effective_date (and corrections). Wrong date → wrong period/day in totals.',
                'severity' => $cross_month ? 'high' : 'medium',
            ],
            'missing_cash_ledger' => [
                'reports' => $all_cash,
                'labels' => array_values($cash_reports),
                'detail' => 'No v2 source and no legacy PAYMENT_CASH for this application month — safe backfill only when nothing represents this application elsewhere.',
                'severity' => 'high',
            ],
            'cash_represented_in_other_period' => [
                'reports' => $all_cash,
                'labels' => array_values($cash_reports),
                'detail' => 'Legacy cash for this payment/application exists in another reporting period. Auto backfill or metadata-only sync would duplicate cash.',
                'severity' => 'high',
            ],
            'unresolved_historical_cash' => [
                'reports' => $all_cash,
                'labels' => array_values($cash_reports),
                'detail' => 'Legacy cash in another period matches amount (or payment) but cannot be tied to this application — manual review before any backfill.',
                'severity' => 'high',
            ],
            'legacy_application_date_drift' => [
                'reports' => $all_cash,
                'labels' => array_values($cash_reports),
                'detail' => 'Legacy rows use metadata payment_history.date, not paymentables.created_at.',
                'severity' => $cross_month ? 'high' : 'low',
            ],
            'ambiguous_legacy_cash_events' => [
                'reports' => $all_cash,
                'labels' => array_values($cash_reports),
                'detail' => 'Multiple legacy PAYMENT_CASH rows in the application month with no unique payment identity match — manual review required.',
                'severity' => 'high',
            ],
            default => [
                'reports' => [],
                'labels' => [],
                'detail' => '',
                'severity' => 'unknown',
            ],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $problems
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $impact
     * @param  array<string, mixed>  $extra
     */
    private function recordProblem(
        array &$problems,
        array &$summary,
        string $issue,
        Paymentable $paymentable,
        int $payment_id,
        int $invoice_id,
        string $intended,
        ?string $payment_date,
        ?int $ledger_event_id,
        ?string $ledger_effective_date,
        array $impact,
        array $extra,
    ): void {
        $problems[] = $this->problemRow(
            $issue,
            $paymentable,
            $payment_id,
            $invoice_id,
            $intended,
            $payment_date,
            $ledger_event_id,
            $ledger_effective_date,
            $impact,
            $extra,
        );
        $summary['by_issue'][$issue] = ($summary['by_issue'][$issue] ?? 0) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function repairRow(
        Paymentable $paymentable,
        int $payment_id,
        int $invoice_id,
        string $issue,
        ?string $ledger_date_before,
        string $intended,
        int $event_id,
    ): array {
        return [
            'outcome' => 'repaired',
            'issue' => $issue,
            'paymentable_id' => $paymentable->id,
            'payment_id' => $payment_id,
            'invoice_id' => $invoice_id,
            'ledger_date_before' => $ledger_date_before,
            'ledger_date_after' => $intended,
            'event_id' => $event_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $impact
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function problemRow(
        string $issue,
        Paymentable $paymentable,
        int $payment_id,
        int $invoice_id,
        string $intended,
        ?string $payment_date,
        ?int $ledger_event_id,
        ?string $ledger_effective_date,
        array $impact,
        array $extra,
    ): array {
        return array_merge([
            'issue' => $issue,
            'paymentable_id' => $paymentable->id,
            'payment_id' => $payment_id,
            'invoice_id' => $invoice_id,
            'amount' => (float) $paymentable->amount,
            'intended' => $intended,
            'payment_date' => $payment_date,
            'ledger_event_id' => $ledger_event_id,
            'ledger_effective_date' => $ledger_effective_date,
            'reports_impacted' => $impact['reports'],
            'report_labels' => $impact['labels'],
            'impact' => $impact['detail'],
            'severity' => $impact['severity'],
        ], $extra);
    }
}
