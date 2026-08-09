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

namespace App\Jobs\EDocument;

use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\ReportData;
use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateReconciler;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateResolver;
use App\Services\EDocument\Standards\France\FranceReportEntryBuilder;
use App\Services\EDocument\Standards\France\FrancePaymentReceivedNotificationEligibility;
use App\Services\EDocument\Standards\France\FranceSubmissionClaim;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use App\Utils\BcMath;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordFranceEReportingPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const KIND_MOVEMENT = 'payment_movement';

    public const KIND_REPORT = 'payment_report';
    public const KIND_PAYMENT_RECEIVED_NOTIFICATION = 'payment_received_notification';

    public const REPORT_KIND_INITIAL = 'initial';

    public const REPORT_KIND_CORRECTIVE = 'corrective';

    private const REPORTING_PATH_F10 = 'f10';

    private const REPORTING_PATH_PAYMENT_RECEIVED_NOTIFICATION = 'payment_received_notification';

    public $deleteWhenMissingModels = true;

    public $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(
        private int $paymentId,
        private string $db,
        private ?int $invoiceId = null,
        private ?int $paymentableId = null,
        private ?string $movementAmount = null,
        private ?string $movementDate = null,
        private string $movementType = FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
        private ?string $movement_identity = null,
    ) {}

    public function handle(): void
    {
        MultiDB::setDb($this->db);

        /** @var Payment|null $payment */
        $payment = Payment::withTrashed()
            ->with([
                'client.country',
                'client.company',
                'company',
                'currency',
            ])
            ->find($this->paymentId);

        if (! $payment || ! $payment->company || ! $this->paymentStatusIsRecordable($payment)) {
            return;
        }

        $reportingPath = $this->resolveReportingPath($payment);

        if (is_null($reportingPath)) {
            return;
        }

        $f10EventId = $reportingPath === self::REPORTING_PATH_F10
            ? $this->resolveF10EventId($payment)
            : null;

        foreach ($this->invoices($payment) as $candidate) {
            try {
                DB::transaction(function () use ($payment, $candidate, $reportingPath, $f10EventId): void {
                    $invoice = Invoice::withTrashed()
                        ->with(['client.country', 'client.company', 'company'])
                        ->lockForUpdate()
                        ->find($candidate->id);

                    if (! $invoice || ! $this->invoiceAllowsMovement($invoice)) {
                        return;
                    }

                    if (app(FranceSubmissionClaim::class)->hasActiveClaimForInvoice($invoice->id, lockForUpdate: true)) {
                        throw new \RuntimeException('France payment reporting is currently being submitted.');
                    }

                    $paymentable = $this->paymentable($payment, $invoice);
                    $movementDate = $this->resolveMovementDate($payment, $paymentable);

                    if (! $movementDate) {
                        return;
                    }

                    if ($reportingPath === self::REPORTING_PATH_PAYMENT_RECEIVED_NOTIFICATION) {
                        if ($this->movementType === FrancePaymentApplicationRecorder::MOVEMENT_APPLIED) {
                            $this->recordPaymentReceivedNotification($invoice, $paymentable);
                        } else {
                            app(FrancePaymentApplicationDateReconciler::class)->reconcilePaymentRemoval(
                                $payment,
                                $invoice,
                                $paymentable->id ?? $this->paymentableId,
                                $movementDate,
                            );
                        }

                        return;
                    }

                    if (! is_null($f10EventId)) {
                        $this->recordF10PaymentMovement($payment, $invoice, $paymentable, $movementDate, $f10EventId);
                    }
                }, attempts: 3);
            } catch (InvalidArgumentException $exception) {
                if ($reportingPath !== self::REPORTING_PATH_F10 || is_null($f10EventId)) {
                    throw $exception;
                }

                report($exception);
                $this->recordF10MappingFailure($payment, $candidate, $f10EventId, $exception);
            }
        }
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->paymentId.($this->invoiceId ?? 'all').($this->paymentableId ?? 'all').$this->db.'.fr-e-reporting-payment'))
                ->releaseAfter(60)
                ->expireAfter(60),
        ];
    }

    /**
     * @return EloquentCollection<int, Invoice>
     */
    private function invoices(Payment $payment): EloquentCollection
    {
        if (! is_null($this->invoiceId)) {
            /** @var EloquentCollection<int, Invoice> $invoices */
            $invoices = Invoice::withTrashed()
                ->with(['client.country', 'client.company', 'company'])
                ->where('id', $this->invoiceId)
                ->get();

            return $invoices;
        }

        /** @var EloquentCollection<int, Invoice> $invoices */
        $invoices = $payment->invoices()
            ->with(['client.country', 'client.company', 'company'])
            ->get();

        return $invoices;
    }

    private function paymentStatusIsRecordable(Payment $payment): bool
    {
        return match ($this->movementType) {
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED => in_array($payment->status_id, [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
            ], true),
            FrancePaymentApplicationRecorder::MOVEMENT_DELETED => in_array($payment->status_id, [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
                Payment::STATUS_CANCELLED,
            ], true),
            default => (int) $payment->status_id === Payment::STATUS_COMPLETED && ! $payment->is_deleted,
        };
    }

    private function resolveReportingPath(Payment $payment): ?string
    {
        if (! $payment->client) {
            return null;
        }

        if (! $payment->client->relationLoaded('company')) {
            $payment->client->setRelation('company', $payment->company);
        }

        if (! $payment->client->reportableFrTransaction()) {
            return null;
        }

        if ($this->paymentRequiresPaymentReceivedNotification($payment)) {
            return self::REPORTING_PATH_PAYMENT_RECEIVED_NOTIFICATION;
        }

        return self::REPORTING_PATH_F10;
    }

    private function paymentRequiresPaymentReceivedNotification(Payment $payment): bool
    {
        return ($payment->client->classification ?? 'business') !== 'individual'
            && $payment->client->country?->iso_3166_2 === 'FR';
    }

    private function invoiceAllowsMovement(Invoice $invoice): bool
    {
        if ($invoice->is_deleted && ! in_array($this->movementType, [
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            FrancePaymentApplicationRecorder::MOVEMENT_DELETED,
        ], true)) {
            return false;
        }

        return true;
    }

    private function recordF10PaymentMovement(Payment $payment, Invoice $invoice, ?Paymentable $paymentable, string $movementDate, int $eventId): void
    {
        if ($eventId === TransactionEvent::FR_B2C_PAYMENT
            && app(FranceReportEntryBuilder::class)->b2cSupplyCategory($invoice) !== 'TPS1') {
            return;
        }

        $movementAmount = $this->resolveMovementAmount($payment, $invoice, $paymentable);

        if (BcMath::isZero($movementAmount, 2)) {
            return;
        }

        $sourcePeriod = $this->resolvePeriodEnd($payment, $invoice, $eventId, $movementDate);
        $movementEvent = $this->recordMovementEvent($payment, $invoice, $paymentable, $eventId, $movementAmount, $movementDate, $sourcePeriod);
        $invoice_is_paid_in_full = $this->invoiceIsPaidInFull($invoice);

        if (BcMath::isNegative($movementAmount, 2)) {
            if (! is_null(data_get($movementEvent->payment_request, 'report_event_id'))) {
                return;
            }

            if (! $invoice_is_paid_in_full) {
                $this->discardPendingInitialReport($payment, $invoice, $eventId);
            }

            $this->applyNegativeReportMovement($payment, $invoice, $eventId, $movementAmount, $movementDate, $movementEvent);
        }

        if ($invoice_is_paid_in_full) {
            $this->promoteFullPaymentReport($payment, $invoice, $eventId, $movementDate);
        }
    }

    private function paymentable(Payment $payment, Invoice $invoice): ?Paymentable
    {
        $query = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices');

        if (! is_null($this->paymentableId)) {
            $query->where('id', $this->paymentableId);
        }

        return $query->latest('id')->first();
    }

    private function resolveMovementAmount(Payment $payment, Invoice $invoice, ?Paymentable $paymentable): string
    {
        if (! is_null($this->movementAmount)) {
            return $this->normalizeAmount($this->movementAmount);
        }

        $amount = $paymentable->amount
            ?? data_get($invoice, 'pivot.amount', $payment->applied ?: $payment->amount ?: 0);

        return $this->normalizeAmount($amount);
    }

    private function resolveMovementDate(Payment $payment, ?Paymentable $paymentable): ?string
    {
        if (in_array($this->movementType, [
            FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            FrancePaymentApplicationRecorder::MOVEMENT_CREDIT_APPLIED,
        ], true)) {
            if (! $paymentable?->created_at) {
                return null;
            }

            $timezone = $payment->company->timezone()?->name ?: config('app.timezone');

            return app(FrancePaymentApplicationDateResolver::class)
                ->resolve($paymentable, $timezone);
        }

        if (! is_null($this->movementDate) && trim($this->movementDate) !== '') {
            return CarbonImmutable::parse($this->movementDate)->toDateString();
        }

        if ($paymentable?->created_at) {
            $timezone = $payment->company->timezone()?->name ?: config('app.timezone');

            return app(FrancePaymentApplicationDateResolver::class)
                ->resolve($paymentable, $timezone);
        }

        return null;
    }

    private function invoiceIsPaidInFull(Invoice $invoice): bool
    {
        $invoice = $invoice->exists ? ($invoice->fresh() ?? $invoice) : $invoice;

        return (int) $invoice->status_id === Invoice::STATUS_PAID
            || BcMath::lessThanOrEqual($invoice->balance ?? 0, '0', 2);
    }

    private function recordPaymentReceivedNotification(Invoice $invoice, ?Paymentable $paymentable): void
    {
        if ($this->movementType !== FrancePaymentApplicationRecorder::MOVEMENT_APPLIED) {
            return;
        }

        if (! $paymentable) {
            return;
        }

        if (! $this->invoiceIsPaidInFull($invoice)) {
            return;
        }

        if ($this->originalDocumentGuid($invoice) === "") {
            return;
        }

        $this->paymentReceivedNotificationEvent($invoice);
    }

    private function paymentReceivedNotificationEvent(Invoice $invoice): ?TransactionEvent
    {
        $originalDocumentGuid = $this->originalDocumentGuid($invoice);
        $paymentable = app(FrancePaymentApplicationDateResolver::class)
            ->latestCompletedInvoiceApplication($invoice->id);

        if (! $paymentable?->payment) {
            return null;
        }

        $payment = $paymentable->payment;
        $timezone = $invoice->company->timezone()?->name ?: config('app.timezone');
        $movementDate = app(FrancePaymentApplicationDateResolver::class)
            ->resolve($paymentable, $timezone);

        if (! $movementDate) {
            return null;
        }
        $matchingEvents = TransactionEvent::query()
            ->where("company_id", $payment->company_id)
            ->where("invoice_id", $invoice->id)
            ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->where("payment_request->fr_kind", self::KIND_PAYMENT_RECEIVED_NOTIFICATION)
            ->where("payment_request->original_document_guid", $originalDocumentGuid)
            ->orderByDesc("id")
            ->get();

        $existing = $matchingEvents->first(
            fn (TransactionEvent $event): bool => $event->payment_status === TransactionEvent::FR_REPORTING_STATUS_SUBMITTED,
        );

        $existing ??= $matchingEvents->first(
            fn (TransactionEvent $event): bool => in_array($event->payment_status, [
                    TransactionEvent::FR_REPORTING_STATUS_PENDING,
                    TransactionEvent::FR_REPORTING_STATUS_FAILED,
                ], true)
                && ! data_get($event->payment_request, 'skip_reason')
                && app(FrancePaymentReceivedNotificationEligibility::class)->isEligible($event),
        );

        if ($existing) {
            return $existing;
        }

        $amount = $this->resolveMovementAmount($payment, $invoice, $paymentable);
        return TransactionEvent::create(array_merge(
            $this->basePayload($payment, $invoice, TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION, $amount, $movementDate),
            [
                "payment_status" => TransactionEvent::FR_REPORTING_STATUS_PENDING,
                "reporting_data" => null,
                "payment_request" => [
                    "fr_kind" => self::KIND_PAYMENT_RECEIVED_NOTIFICATION,
                    "source_date" => $movementDate,
                    "paymentable_id" => $paymentable->id,
                    "movement_type" => $this->movementType,
                    "original_document_guid" => $originalDocumentGuid,
                    "idempotency_guid" => Str::uuid()->toString(),
                    "mode" => "auto",
                ],
            ]
        ));
    }

    private function originalDocumentGuid(Invoice $invoice): string
    {
        return trim((string) ($invoice->backup->guid ?? ""));
    }

    private function resolveF10EventId(Payment $payment): int
    {
        if (($payment->client->classification ?? 'business') === 'individual') {
            return TransactionEvent::FR_B2C_PAYMENT;
        }

        return TransactionEvent::FR_VAT_EXCLUDED_PAYMENT;
    }

    private function resolvePeriodEnd(Payment $payment, Invoice $invoice, int $eventId, string $date): string
    {
        return ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse($date ?: $payment->date ?: $invoice->date ?: now()->toDateString()),
        )->end->toDateString();
    }

    private function recordMovementEvent(
        Payment $payment,
        Invoice $invoice,
        ?Paymentable $paymentable,
        int $eventId,
        string $movementAmount,
        string $movementDate,
        string $sourcePeriod,
    ): TransactionEvent {
        $paymentableId = $paymentable->id ?? $this->paymentableId;
        $snapshotHash = $this->movementSnapshotHash($payment, $invoice, $paymentableId, $eventId, $movementAmount, $movementDate);

        if ($paymentableId && in_array($this->movementType, [
            FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            FrancePaymentApplicationRecorder::MOVEMENT_CREDIT_APPLIED,
        ], true)) {
            $stable = TransactionEvent::query()
                ->where('company_id', $payment->company_id)
                ->where('payment_id', $payment->id)
                ->where('invoice_id', $invoice->id)
                ->where('event_id', $eventId)
                ->orderByDesc('id')
                ->get()
                ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === self::KIND_MOVEMENT
                    && (int) data_get($event->payment_request, 'paymentable_id') === (int) $paymentableId
                    && data_get($event->payment_request, 'movement_type') === $this->movementType);

            if ($stable) {
                return $stable;
            }
        }

        $existing = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('payment_id', $payment->id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_DEFERRED)
            ->get()
            ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'snapshot_hash') === $snapshotHash);

        if ($existing) {
            return $existing;
        }

        return TransactionEvent::create(array_merge(
            $this->basePayload($payment, $invoice, $eventId, $movementAmount, $sourcePeriod),
            [
                'payment_status' => TransactionEvent::FR_REPORTING_STATUS_DEFERRED,
                'reporting_data' => null,
                'payment_request' => [
                    'fr_kind' => self::KIND_MOVEMENT,
                    'source_date' => $movementDate,
                    'paymentable_id' => $paymentableId,
                    'movement_type' => $this->movementType,
                    'movement_amount' => $movementAmount,
                    'snapshot_hash' => $snapshotHash,
                    'report_event_id' => null,
                ],
            ]
        ));
    }

    private function promoteFullPaymentReport(Payment $payment, Invoice $invoice, int $eventId, string $fallbackDate): void
    {
        $movements = $this->unreportedMovementEvents($payment, $invoice, $eventId);

        if ($movements->isEmpty()) {
            return;
        }

        $aggregateAmount = $this->normalizeAmount($this->sumMovementAmounts($movements));

        if (BcMath::isZero($aggregateAmount, 2)) {
            return;
        }

        $reportDate = $this->latestMovementDate($movements, $fallbackDate);
        $submittedReport = $this->latestSubmittedReportEvent($payment, $invoice, $eventId);

        if ($submittedReport) {
            $this->createOrUpdateReportEvent(
                payment: $payment,
                invoice: $invoice,
                eventId: $eventId,
                amount: $aggregateAmount,
                reportDate: $reportDate,
                sourceEvents: $movements,
                reportKind: self::REPORT_KIND_CORRECTIVE,
                previousEventId: $submittedReport->id,
            );

            return;
        }

        $this->createOrUpdateReportEvent(
            payment: $payment,
            invoice: $invoice,
            eventId: $eventId,
            amount: $aggregateAmount,
            reportDate: $reportDate,
            sourceEvents: $movements,
            reportKind: self::REPORT_KIND_INITIAL,
        );
    }

    private function applyNegativeReportMovement(
        Payment $payment,
        Invoice $invoice,
        int $eventId,
        string $movementAmount,
        string $movementDate,
        TransactionEvent $movementEvent,
    ): void {
        $pendingReport = $this->pendingReportEvent($payment, $invoice, $eventId);

        if ($pendingReport) {
            $this->updatePendingReportEvent($pendingReport, $payment, $invoice, collect([$movementEvent]), $movementAmount, $movementDate);

            return;
        }

        $submittedReport = $this->latestSubmittedReportEvent($payment, $invoice, $eventId);

        if (! $submittedReport) {
            return;
        }

        $this->createOrUpdateReportEvent(
            payment: $payment,
            invoice: $invoice,
            eventId: $eventId,
            amount: $movementAmount,
            reportDate: $movementDate,
            sourceEvents: collect([$movementEvent]),
            reportKind: self::REPORT_KIND_CORRECTIVE,
            previousEventId: $submittedReport->id,
        );
    }

    private function discardPendingInitialReport(Payment $payment, Invoice $invoice, int $eventId): void
    {
        $pending_report = $this->pendingReportEvent(
            $payment,
            $invoice,
            $eventId,
            self::REPORT_KIND_INITIAL,
        );

        if (! $pending_report) {
            return;
        }

        $this->unlinkMovementEvents(
            data_get($pending_report->payment_request, 'source_event_ids', []),
        );
        $pending_report->delete();
    }

    /**
     * @return EloquentCollection<int, TransactionEvent>
     */
    private function unreportedMovementEvents(Payment $payment, Invoice $invoice, int $eventId): EloquentCollection
    {
        /** @var EloquentCollection<int, TransactionEvent> $events */
        $events = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_DEFERRED)
            ->orderBy('id')
            ->get()
            ->filter(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === self::KIND_MOVEMENT
                && is_null(data_get($event->payment_request, 'report_event_id')))
            ->values();

        return $events;
    }

    private function pendingReportEvent(Payment $payment, Invoice $invoice, int $eventId, ?string $reportKind = null, ?int $previousEventId = null): ?TransactionEvent
    {
        return TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->whereIn('payment_status', [TransactionEvent::FR_REPORTING_STATUS_PENDING, TransactionEvent::FR_REPORTING_STATUS_FAILED])
            ->orderByDesc('id')
            ->get()
            ->first(function (TransactionEvent $event) use ($reportKind, $previousEventId): bool {
                if (data_get($event->payment_request, 'fr_kind') !== self::KIND_REPORT) {
                    return false;
                }

                if (data_get($event->payment_request, 'skip_reason')) {
                    return false;
                }

                if (! is_null($reportKind) && data_get($event->payment_request, 'fr_report_kind') !== $reportKind) {
                    return false;
                }

                if (! is_null($previousEventId) && (int) data_get($event->payment_request, 'previous_event_id') !== $previousEventId) {
                    return false;
                }

                return true;
            });
    }

    private function latestSubmittedReportEvent(Payment $payment, Invoice $invoice, int $eventId): ?TransactionEvent
    {
        return TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', $eventId)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
            ->orderByDesc('id')
            ->get()
            ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === self::KIND_REPORT);
    }

    /**
     * @param iterable<int, TransactionEvent> $sourceEvents
     */
    private function createOrUpdateReportEvent(
        Payment $payment,
        Invoice $invoice,
        int $eventId,
        string $amount,
        string $reportDate,
        iterable $sourceEvents,
        string $reportKind,
        ?int $previousEventId = null,
    ): void {
        $amount = $this->normalizeAmount($amount);
        $sourceEvents = collect($sourceEvents)->values();
        $existing = $this->pendingReportEvent($payment, $invoice, $eventId, $reportKind, $previousEventId);

        if ($existing) {
            $this->updatePendingReportEvent($existing, $payment, $invoice, $sourceEvents, $amount, $reportDate);

            return;
        }

        $period = $this->resolvePeriodEnd($payment, $invoice, $eventId, $reportDate);
        $event = TransactionEvent::create(array_merge(
            $this->basePayload($payment, $invoice, $eventId, $amount, $period),
            [
                'payment_status' => TransactionEvent::FR_REPORTING_STATUS_PENDING,
                'reporting_data' => $this->reportingData($payment, $invoice, $eventId, $amount, $reportDate),
                'payment_request' => $this->reportPaymentRequest($reportDate, $sourceEvents, $reportKind, $previousEventId),
            ]
        ));

        $this->linkMovementEvents($sourceEvents, $event->id);
    }

    /**
     * @param iterable<int, TransactionEvent> $sourceEvents
     */
    private function updatePendingReportEvent(TransactionEvent $event, Payment $payment, Invoice $invoice, iterable $sourceEvents, string $amount, string $reportDate): void
    {
        $sourceEvents = collect($sourceEvents)->values();
        $request = $event->payment_request ?? [];
        $aggregateAmount = $this->normalizeAmount(BcMath::add($event->payment_applied ?: 0, $amount, 2));
        $sourceEventIds = collect(data_get($request, 'source_event_ids', []))
            ->merge($sourceEvents->pluck('id'))
            ->unique()
            ->values()
            ->all();

        if (BcMath::isZero($aggregateAmount, 2)) {
            $this->unlinkMovementEvents($sourceEventIds);
            $event->delete();

            return;
        }

        $reportDate = max((string) data_get($request, 'source_date', $reportDate), $reportDate);
        $period = $this->resolvePeriodEnd($payment, $invoice, $event->event_id, $reportDate);

        $request['source_date'] = $reportDate;
        $request['source_event_ids'] = $sourceEventIds;

        $event->period = \Carbon\Carbon::parse($period);
        $event->payment_applied = (float) $aggregateAmount;
        $event->payment_request = $request;
        $event->reporting_data = $this->reportingData($payment, $invoice, $event->event_id, $aggregateAmount, $reportDate);
        $event->save();

        $this->linkMovementEvents($sourceEvents, $event->id);
    }

    /**
     * @param iterable<int, TransactionEvent> $sourceEvents
     * @return array<string, mixed>
     */
    private function reportPaymentRequest(
        string $reportDate,
        iterable $sourceEvents,
        string $reportKind,
        ?int $previousEventId,
    ): array {
        $sourceEvents = collect($sourceEvents)->values();
        $sourceEventIds = $sourceEvents->pluck('id')->values()->all();

        return [
            'fr_kind' => self::KIND_REPORT,
            'fr_report_kind' => $reportKind,
            'source_date' => $reportDate,
            'source_event_ids' => $sourceEventIds,
            'previous_event_id' => $previousEventId,
        ];
    }

    /**
     * @param iterable<int, TransactionEvent> $events
     */
    private function linkMovementEvents(iterable $events, int $reportEventId): void
    {
        foreach ($events as $event) {
            $request = $event->payment_request ?? [];
            $request['report_event_id'] = $reportEventId;
            $event->payment_request = $request;
            $event->save();
        }
    }

    /**
     * @param array<int, int> $eventIds
     */
    private function unlinkMovementEvents(array $eventIds): void
    {
        TransactionEvent::query()
            ->whereIn('id', $eventIds)
            ->get()
            ->each(function (TransactionEvent $event): void {
                $request = $event->payment_request ?? [];
                $request['report_event_id'] = null;
                $event->payment_request = $request;
                $event->save();
            });
    }

    /**
     * @param iterable<int, TransactionEvent> $events
     */
    private function sumMovementAmounts(iterable $events): string
    {
        return collect($events)->reduce(
            fn (string $carry, TransactionEvent $event): string => BcMath::add($carry, data_get($event->payment_request, 'movement_amount', $event->payment_applied), 2),
            '0',
        );
    }

    /**
     * @param iterable<int, TransactionEvent> $events
     */
    private function latestMovementDate(iterable $events, string $fallbackDate): string
    {
        return collect($events)
            ->map(fn (TransactionEvent $event): ?string => data_get($event->payment_request, 'source_date'))
            ->filter()
            ->push($fallbackDate)
            ->max();
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

    private function recordF10MappingFailure(Payment $payment, Invoice $invoice, int $eventId, InvalidArgumentException $exception): void
    {
        $paymentable = $this->paymentable($payment, $invoice);
        $movementDate = $this->resolveMovementDate($payment, $paymentable)
            ?? CarbonImmutable::parse($payment->date ?: now())->toDateString();
        $amount = $this->resolveMovementAmount($payment, $invoice, $paymentable);
        $period = $this->resolvePeriodEnd($payment, $invoice, $eventId, $movementDate);

        $existing = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('payment_id', $payment->id)
            ->where('event_id', $eventId)
            ->whereNotNull('payment_request->skip_reason')
            ->exists();

        if ($existing) {
            return;
        }

        TransactionEvent::create(array_merge(
            $this->basePayload($payment, $invoice, $eventId, $amount, $period),
            [
                'payment_status' => TransactionEvent::FR_REPORTING_STATUS_FAILED,
                'reporting_data' => null,
                'payment_request' => [
                    'fr_kind' => self::KIND_REPORT,
                    'fr_report_kind' => BcMath::isNegative($amount, 2)
                        ? self::REPORT_KIND_CORRECTIVE
                        : self::REPORT_KIND_INITIAL,
                    'source_date' => $movementDate,
                    'source_event_ids' => [],
                    'error' => [
                        'message' => $exception->getMessage(),
                        'class' => $exception::class,
                    ],
                    'skip_reason' => 'France e-report payment mapping failed.',
                    'skipped_at' => now()->toIso8601String(),
                ],
            ],
        ));
    }

    private function movementSnapshotHash(Payment $payment, Invoice $invoice, ?int $paymentableId, int $eventId, string $movementAmount, string $movementDate): string
    {
        return sha1(json_encode([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'paymentable_id' => $paymentableId,
            'event_id' => $eventId,
            'movement_type' => $this->movementType,
            'movement_amount' => $movementAmount,
            'movement_date' => $movementDate,
            'movement_identity' => $this->movement_identity,
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
