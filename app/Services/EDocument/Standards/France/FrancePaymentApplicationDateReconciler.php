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

use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\ReportData;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Utils\BcMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class FrancePaymentApplicationDateReconciler
{
    public function reconcilePaymentRemoval(
        Payment $payment,
        Invoice $invoice,
        ?int $paymentableId,
        string $effectiveDate,
    ): void {
        if (! $this->requiresPaymentReceivedNotification($payment)) {
            return;
        }

        $this->assertNoActiveSubmissionClaim($invoice->id);

        $paymentables = new EloquentCollection();

        if ($paymentableId) {
            $paymentable = Paymentable::withTrashed()->find($paymentableId);

            if ($paymentable) {
                $paymentables->push($paymentable);
            }
        }

        $this->reconcilePaymentReceivedNotification(
            $payment,
            $invoice,
            $effectiveDate,
            $effectiveDate,
            $paymentables,
        );
    }

    /**
     * @param array<int, int> $paymentableIds
     */
    public function reconcile(
        int $invoiceId,
        int $paymentId,
        string $oldDate,
        string $newDate,
        array $paymentableIds,
    ): void {
        DB::transaction(function () use ($invoiceId, $paymentId, $oldDate, $newDate, $paymentableIds): void {
            $invoice = Invoice::withTrashed()
                ->with(['client.country', 'client.company', 'company'])
                ->lockForUpdate()
                ->find($invoiceId);
            $payment = Payment::withTrashed()
                ->with(['client.country', 'client.company', 'company', 'currency'])
                ->find($paymentId);

            if (! $invoice
                || ! $payment
                || (int) $invoice->company_id !== (int) $payment->company_id
                || ! $invoice->client->reportableFrTransaction()) {
                return;
            }

            $this->assertNoActiveSubmissionClaim($invoice->id);

            $paymentables = Paymentable::withTrashed()
                ->where('payment_id', $payment->id)
                ->where('paymentable_type', 'invoices')
                ->where('paymentable_id', $invoice->id)
                ->whereIn('id', $paymentableIds)
                ->orderBy('id')
                ->get();

            if ($paymentables->isEmpty()) {
                return;
            }

            if ($this->requiresPaymentReceivedNotification($payment)) {
                $this->reconcilePaymentReceivedNotification($payment, $invoice, $oldDate, $newDate, $paymentables);

                return;
            }

            $eventId = $this->f10EventId($payment);

            if ($eventId === TransactionEvent::FR_B2C_PAYMENT
                && app(FranceReportEntryBuilder::class)->b2cSupplyCategory($invoice) !== 'TPS1') {
                $this->discardMutableInitialReport($payment, $invoice, $eventId);

                return;
            }

            foreach ($paymentables as $paymentable) {
                $this->reconcileF10Movement($payment, $invoice, $paymentable, $eventId, $oldDate, $newDate);
            }
        }, attempts: 3);
    }

    private function assertNoActiveSubmissionClaim(int $invoiceId): void
    {
        if (app(FranceSubmissionClaim::class)->hasActiveClaimForInvoice($invoiceId, lockForUpdate: true)) {
            throw new RuntimeException('France payment reporting is currently being submitted.');
        }
    }

    private function reconcileF10Movement(
        Payment $payment,
        Invoice $invoice,
        Paymentable $paymentable,
        int $eventId,
        string $oldDate,
        string $newDate,
    ): void {
        $movement = $this->appliedMovement($payment, $invoice, $paymentable, $eventId);
        $movementDate = $this->paymentableDate($paymentable, $payment);
        $movementAmount = $this->normalizeAmount($paymentable->amount);

        if ($movement && $this->movementWasSubmitted($movement)) {
            if ((string) data_get($movement->payment_request, 'source_date') !== $movementDate) {
                $this->recordComplianceException(
                    $payment,
                    $invoice,
                    $eventId,
                    $paymentable->id,
                    $oldDate,
                    $newDate,
                    'Submitted F10 payment date corrections require a confirmed Storecove rectificative payload.',
                );
            }

            return;
        }

        if (! $movement) {
            $movement = new TransactionEvent();
        }

        $request = $movement->payment_request ?? [];
        $request = [
            ...$request,
            'fr_kind' => RecordFranceEReportingPayment::KIND_MOVEMENT,
            'source_date' => $movementDate,
            'paymentable_id' => $paymentable->id,
            'movement_type' => FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            'movement_amount' => $movementAmount,
            'snapshot_hash' => $this->movementSnapshotHash($payment, $invoice, $paymentable->id, $eventId, $movementAmount, $movementDate),
            'report_event_id' => data_get($request, 'report_event_id'),
        ];

        $movement->fill([
            ...$this->basePayload($payment, $invoice, $eventId, $movementAmount, $this->periodEnd($payment, $eventId, $movementDate)),
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_DEFERRED,
            'reporting_data' => null,
            'payment_request' => $request,
        ]);
        $movement->save();

        $reportEventId = (int) data_get($request, 'report_event_id', 0);

        if ($reportEventId > 0) {
            $report = TransactionEvent::query()->lockForUpdate()->find($reportEventId);

            if ($report && in_array($report->payment_status, [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_FAILED,
            ], true)) {
                $this->rebuildMutableReport($payment, $invoice, $report);
            }

            return;
        }

        if ($this->invoiceIsPaidInFull($invoice)) {
            $this->promoteUnlinkedMovements($payment, $invoice, $eventId, $movementDate);
        }
    }

    private function rebuildMutableReport(Payment $payment, Invoice $invoice, TransactionEvent $report): void
    {
        $sourceIds = collect(data_get($report->payment_request, 'source_event_ids', []))
            ->map(fn($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $movements = $this->movementEvents($invoice, $report->event_id, $sourceIds->all());

        if ($movements->isEmpty()) {
            $report->delete();

            return;
        }

        if (data_get($report->payment_request, 'fr_report_kind') === RecordFranceEReportingPayment::REPORT_KIND_INITIAL
            && ! $this->invoiceIsPaidInFull($invoice)) {
            $this->unlinkMovements($movements);
            $report->delete();

            return;
        }

        $amount = $this->sumMovementAmounts($movements);

        if (BcMath::isZero($amount, 2)) {
            $this->unlinkMovements($movements);
            $report->delete();

            return;
        }

        $reportDate = $this->latestMovementDate($movements);
        $request = $report->payment_request ?? [];
        $request['source_date'] = $reportDate;
        $request['source_event_ids'] = $movements->pluck('id')->map(fn($id): int => (int) $id)->values()->all();

        $report->fill([
            ...$this->basePayload($payment, $invoice, $report->event_id, $amount, $this->periodEnd($payment, $report->event_id, $reportDate)),
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_PENDING,
            'reporting_data' => $this->reportingData($payment, $invoice, $report->event_id, $amount, $reportDate),
            'payment_request' => $request,
        ]);
        $report->save();
        $this->linkMovements($movements, $report->id);
    }

    private function promoteUnlinkedMovements(Payment $payment, Invoice $invoice, int $eventId, string $fallbackDate): void
    {
        $movements = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_DEFERRED)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT
                && is_null(data_get($event->payment_request, 'report_event_id')))
            ->values();

        if ($movements->isEmpty()) {
            return;
        }

        $amount = $this->sumMovementAmounts($movements);

        if (BcMath::isZero($amount, 2)) {
            return;
        }

        $submitted = $this->latestSubmittedReport($payment, $invoice, $eventId);

        if ($submitted) {
            foreach ($movements as $movement) {
                $this->recordComplianceException(
                    $payment,
                    $invoice,
                    $eventId,
                    (int) data_get($movement->payment_request, 'paymentable_id'),
                    (string) data_get($movement->payment_request, 'source_date', $fallbackDate),
                    $fallbackDate,
                    'Submitted F10 payment date corrections require a confirmed Storecove rectificative payload.',
                );
            }

            return;
        }

        $reportDate = $this->latestMovementDate($movements, $fallbackDate);
        $report = $this->pendingInitialReport($payment, $invoice, $eventId) ?? new TransactionEvent();
        $request = $report->payment_request ?? [];
        $request = [
            ...$request,
            'fr_kind' => RecordFranceEReportingPayment::KIND_REPORT,
            'fr_report_kind' => RecordFranceEReportingPayment::REPORT_KIND_INITIAL,
            'source_date' => $reportDate,
            'source_event_ids' => $movements->pluck('id')->map(fn($id): int => (int) $id)->values()->all(),
            'previous_event_id' => null,
        ];

        $report->fill([
            ...$this->basePayload($payment, $invoice, $eventId, $amount, $this->periodEnd($payment, $eventId, $reportDate)),
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_PENDING,
            'reporting_data' => $this->reportingData($payment, $invoice, $eventId, $amount, $reportDate),
            'payment_request' => $request,
        ]);
        $report->save();
        $this->linkMovements($movements, $report->id);
    }

    /**
     * @param EloquentCollection<int, Paymentable> $changedPaymentables
     */
    private function reconcilePaymentReceivedNotification(
        Payment $payment,
        Invoice $invoice,
        string $oldDate,
        string $newDate,
        EloquentCollection $changedPaymentables,
    ): void {
        $guid = trim((string) ($invoice->backup->guid ?? ''));

        if ($guid === '') {
            return;
        }

        $events = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_PAYMENT_RECEIVED_NOTIFICATION
                && (string) data_get($event->payment_request, 'original_document_guid') === $guid)
            ->values();

        $submitted = $events->first(fn(TransactionEvent $event): bool => $event->payment_status === TransactionEvent::FR_REPORTING_STATUS_SUBMITTED);
        $paymentable = app(FrancePaymentApplicationDateResolver::class)
            ->latestCompletedInvoiceApplication($invoice->id);
        $collectionDate = $paymentable
            ? $this->paymentableDate($paymentable, $paymentable->payment)
            : null;

        if ($submitted) {
            if (! $this->invoiceIsPaidInFull($invoice)
                || ! $collectionDate
                || (string) data_get($submitted->payment_request, 'source_date') !== $collectionDate) {
                $changedPaymentableId = (int) ($changedPaymentables->first()->id
                    ?? data_get($submitted->payment_request, 'paymentable_id', 0));
                $this->recordComplianceException(
                    $payment,
                    $invoice,
                    TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION,
                    $changedPaymentableId,
                    $oldDate,
                    $newDate,
                    'Storecove does not expose a reversal or date-correction operation for a submitted payment notification.',
                );
            }

            return;
        }

        if (! $this->invoiceIsPaidInFull($invoice) || ! $paymentable || ! $collectionDate) {
            $events->each->delete();

            return;
        }

        $canonical_payment = $paymentable->payment;
        $event = $events->first() ?? new TransactionEvent();
        $request = $event->payment_request ?? [];
        $request = [
            ...$request,
            'fr_kind' => RecordFranceEReportingPayment::KIND_PAYMENT_RECEIVED_NOTIFICATION,
            'source_date' => $collectionDate,
            'paymentable_id' => $paymentable->id,
            'movement_type' => FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            'original_document_guid' => $guid,
            'idempotency_guid' => data_get($request, 'idempotency_guid') ?: Str::uuid()->toString(),
            'mode' => 'auto',
            'error' => null,
            'skip_reason' => null,
        ];

        $event->fill([
            ...$this->basePayload($canonical_payment, $invoice, TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION, (string) $paymentable->amount, $collectionDate),
            'payment_id' => $canonical_payment->id,
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_PENDING,
            'reporting_data' => null,
            'payment_request' => $request,
        ]);
        $event->save();

        $events->where('id', '!=', $event->id)->each->delete();
    }

    private function recordComplianceException(
        Payment $payment,
        Invoice $invoice,
        int $eventId,
        int $paymentableId,
        string $oldDate,
        string $newDate,
        string $reason,
    ): void {
        $key = sha1(implode('|', [$payment->company_id, $invoice->id, $payment->id, $paymentableId, $eventId, $oldDate, $newDate]));
        $existing = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_FAILED)
            ->lockForUpdate()
            ->get()
            ->first(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'correction_key') === $key);

        if ($existing) {
            return;
        }

        TransactionEvent::create([
            ...$this->basePayload($payment, $invoice, $eventId, '0', $newDate),
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_FAILED,
            'reporting_data' => null,
            'payment_request' => [
                'fr_kind' => 'payment_date_correction_unsupported',
                'paymentable_id' => $paymentableId,
                'source_date' => $newDate,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'correction_key' => $key,
                'skip_reason' => $reason,
                'error' => ['message' => $reason],
            ],
        ]);
    }

    private function appliedMovement(Payment $payment, Invoice $invoice, Paymentable $paymentable, int $eventId): ?TransactionEvent
    {
        return TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('payment_id', $payment->id)
            ->where('event_id', $eventId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get()
            ->first(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT
                && (int) data_get($event->payment_request, 'paymentable_id') === (int) $paymentable->id
                && data_get($event->payment_request, 'movement_type') === FrancePaymentApplicationRecorder::MOVEMENT_APPLIED);
    }

    private function movementWasSubmitted(TransactionEvent $movement): bool
    {
        $reportId = (int) data_get($movement->payment_request, 'report_event_id', 0);

        return $reportId > 0
            && TransactionEvent::query()
                ->where('id', $reportId)
                ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
                ->exists();
    }

    private function latestSubmittedReport(Payment $payment, Invoice $invoice, int $eventId): ?TransactionEvent
    {
        return TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
            ->orderByDesc('id')
            ->get()
            ->first(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_REPORT);
    }

    private function pendingInitialReport(Payment $payment, Invoice $invoice, int $eventId): ?TransactionEvent
    {
        return TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->whereIn('payment_status', [TransactionEvent::FR_REPORTING_STATUS_PENDING, TransactionEvent::FR_REPORTING_STATUS_FAILED])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get()
            ->first(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_REPORT
                && data_get($event->payment_request, 'fr_report_kind', RecordFranceEReportingPayment::REPORT_KIND_INITIAL) === RecordFranceEReportingPayment::REPORT_KIND_INITIAL);
    }

    private function discardMutableInitialReport(Payment $payment, Invoice $invoice, int $eventId): void
    {
        $report = $this->pendingInitialReport($payment, $invoice, $eventId);

        if (! $report) {
            return;
        }

        $sourceIds = collect(data_get($report->payment_request, 'source_event_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->unlinkMovements($this->movementEvents($invoice, $eventId, $sourceIds));
        $report->delete();
    }

    /**
     * @param array<int, int> $sourceIds
     * @return EloquentCollection<int, TransactionEvent>
     */
    private function movementEvents(Invoice $invoice, int $eventId, array $sourceIds): EloquentCollection
    {
        /** @var EloquentCollection<int, TransactionEvent> $events */
        $events = TransactionEvent::query()
            ->where('company_id', $invoice->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->whereIn('id', $sourceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT)
            ->values();

        return $events;
    }

    /**
     * @param iterable<int, TransactionEvent> $movements
     */
    private function linkMovements(iterable $movements, int $reportId): void
    {
        foreach ($movements as $movement) {
            $request = $movement->payment_request ?? [];
            $request['report_event_id'] = $reportId;
            $movement->payment_request = $request;
            $movement->save();
        }
    }

    /**
     * @param iterable<int, TransactionEvent> $movements
     */
    private function unlinkMovements(iterable $movements): void
    {
        foreach ($movements as $movement) {
            $request = $movement->payment_request ?? [];
            $request['report_event_id'] = null;
            $movement->payment_request = $request;
            $movement->save();
        }
    }

    /**
     * @param iterable<int, TransactionEvent> $movements
     */
    private function sumMovementAmounts(iterable $movements): string
    {
        return $this->normalizeAmount(collect($movements)->reduce(
            fn(string $carry, TransactionEvent $movement): string => BcMath::add(
                $carry,
                data_get($movement->payment_request, 'movement_amount', $movement->payment_applied),
                2,
            ),
            '0',
        ));
    }

    /**
     * @param iterable<int, TransactionEvent> $movements
     */
    private function latestMovementDate(iterable $movements, ?string $fallback = null): string
    {
        return (string) collect($movements)
            ->map(fn(TransactionEvent $movement): ?string => data_get($movement->payment_request, 'source_date'))
            ->filter()
            ->when($fallback, fn($dates) => $dates->push($fallback))
            ->max();
    }

    private function invoiceIsPaidInFull(Invoice $invoice): bool
    {
        $fresh = $invoice->fresh() ?? $invoice;

        return (int) $fresh->status_id === Invoice::STATUS_PAID
            || BcMath::lessThanOrEqual($fresh->balance ?? 0, '0', 2);
    }

    private function requiresPaymentReceivedNotification(Payment $payment): bool
    {
        return ($payment->client->classification ?? 'business') !== 'individual'
            && $payment->client->country?->iso_3166_2 === 'FR';
    }

    private function f10EventId(Payment $payment): int
    {
        return ($payment->client->classification ?? 'business') === 'individual'
            ? TransactionEvent::FR_B2C_PAYMENT
            : TransactionEvent::FR_VAT_EXCLUDED_PAYMENT;
    }

    private function periodEnd(Payment $payment, int $eventId, string $date): string
    {
        $profile = $eventId === TransactionEvent::FR_VAT_EXCLUDED_PAYMENT
            ? ReportingProfile::BiMonthly
            : (ReportingProfile::tryFrom((string) $payment->company->getSetting('france_reporting_schedule')) ?? ReportingProfile::TenDay);

        return ReportingCalendar::currentPeriod($profile, CarbonImmutable::parse($date))->end->toDateString();
    }

    private function paymentableDate(Paymentable $paymentable, Payment $payment): string
    {
        $timezone = $payment->company->timezone()?->name ?: config('app.timezone');

        return app(FrancePaymentApplicationDateResolver::class)
            ->resolve($paymentable, $payment->date, $timezone)
            ?? throw new InvalidArgumentException('Payment application date is unavailable.');
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(Payment $payment, Invoice $invoice, int $eventId, string $amount, string $period): array
    {
        return [
            'company_id' => $payment->company_id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'client_balance' => $invoice->client->balance,
            'client_paid_to_date' => $invoice->client->paid_to_date,
            'client_credit_balance' => $invoice->client->credit_balance,
            'invoice_balance' => $invoice->balance ?? 0,
            'invoice_amount' => $invoice->amount ?? 0,
            'invoice_partial' => $invoice->partial ?? 0,
            'invoice_paid_to_date' => $invoice->paid_to_date ?? 0,
            'invoice_status' => $invoice->status_id,
            'payment_amount' => $payment->amount ?? 0,
            'payment_applied' => (float) $amount,
            'payment_refunded' => $payment->refunded ?? 0,
            'event_id' => $eventId,
            'timestamp' => now()->timestamp,
            'period' => $period,
            'credit_balance' => 0,
            'credit_amount' => 0,
            'credit_status' => null,
        ];
    }

    private function reportingData(Payment $payment, Invoice $invoice, int $eventId, string $amount, string $paymentDate): ReportData
    {
        /** @var FranceReportEntryBuilder $builder */
        $builder = app(FranceReportEntryBuilder::class);

        return match ($eventId) {
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT => ReportData::fromFRReportEntry(
                FRReportEntryData::fromB2BIPayment($builder->b2biPayment($payment, $invoice, $amount, $paymentDate)),
            ),
            TransactionEvent::FR_B2C_PAYMENT => ReportData::fromFRReportEntry(
                FRReportEntryData::fromB2CPayment($builder->b2cPayment($payment, $invoice, $amount, $paymentDate)),
            ),
            default => throw new InvalidArgumentException("Unsupported France payment event_id [{$eventId}]."),
        };
    }

    private function movementSnapshotHash(
        Payment $payment,
        Invoice $invoice,
        int $paymentableId,
        int $eventId,
        string $amount,
        string $date,
    ): string {
        return sha1(json_encode([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'paymentable_id' => $paymentableId,
            'event_id' => $eventId,
            'movement_type' => FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            'movement_amount' => $amount,
            'movement_date' => $date,
        ], JSON_THROW_ON_ERROR));
    }

    private function normalizeAmount(int|float|string|null $amount): string
    {
        $amount = BcMath::round($amount ?? 0, 2);

        if (str_ends_with($amount, '.00')) {
            return substr($amount, 0, -3);
        }

        return rtrim(rtrim($amount, '0'), '.');
    }
}
