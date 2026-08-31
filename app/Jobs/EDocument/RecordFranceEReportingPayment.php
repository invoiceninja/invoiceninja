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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateResolver;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use App\Utils\BcMath;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Append one immutable payment fact. Projection and filing happen later.
 */
class RecordFranceEReportingPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(
        private int $paymentId,
        private string $db,
        private int $invoiceId,
        private ?int $paymentableId,
        private string $movementAmount,
        private string $movementDate,
        private string $movementType = FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
        private ?string $movementIdentity = null,
        private ?string $reportingPath = null,
        private ?string $originalDocumentGuid = null,
        private ?string $sourceRevision = null,
    ) {}

    public function handle(): void
    {
        MultiDB::setDb($this->db);

        /** @var Payment|null $payment */
        $payment = Payment::withTrashed()
            ->with('company')
            ->find($this->paymentId);

        if (! $payment
            || ! $payment->company
            || ! (bool) $payment->company->getSetting('france_reporting_enabled')) {
            return;
        }

        $payment->loadMissing(['client.country', 'client.company', 'currency']);

        if (! $payment->client) {
            return;
        }

        if (! $payment->client->relationLoaded('company')) {
            $payment->client->setRelation('company', $payment->company);
        }

        $invoice = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->find($this->invoiceId);

        if ($invoice) {
            $this->recordFact($payment, $invoice);
        }
    }

    private function recordFact(Payment $payment, Invoice $invoice): void
    {
        $paymentable = $this->paymentable($payment, $invoice);
        $movementDate = $this->resolveMovementDate($payment, $paymentable);

        if (! $movementDate) {
            return;
        }

        $amount = $this->normalizeAmount($this->movementAmount);

        if (BcMath::isZero($amount, 2)) {
            return;
        }

        $path = $this->reportingPath ?? $this->reportingPath($payment);
        $f10PaymentKind = $path === 'f10'
            ? (($invoice->client->classification ?? 'business') === 'individual' ? 'b2c' : 'b2bi')
            : null;
        $subjectKey = "payment:{$payment->id}:invoice:{$invoice->id}";
        $projectionGate = strtoupper((string) $invoice->client->currency()?->code) === 'EUR'
            ? null
            : 'non_eur_payment_mapping_unconfirmed';
        $operationKey = hash('sha256', json_encode([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'paymentable_id' => $paymentable->id ?? $this->paymentableId,
            'movement_type' => $this->movementType,
            'movement_amount' => $amount,
            'movement_date' => $movementDate,
            'movement_identity' => $this->movementIdentity,
        ], JSON_THROW_ON_ERROR));

        DB::transaction(function () use (
            $payment,
            $invoice,
            $paymentable,
            $operationKey,
            $amount,
            $path,
            $f10PaymentKind,
            $subjectKey,
            $movementDate,
            $projectionGate,
        ): void {
            $firstMovement = TransactionEvent::query()
                ->where('company_id', $payment->company_id)
                ->where('invoice_id', $invoice->id)
                ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                ->oldest('id')
                ->first(['payment_request']);
            $reportingPath = (string) data_get($firstMovement?->payment_request, 'reporting_path', $path);
            $reportingKind = $reportingPath === 'f10'
                ? (string) data_get($firstMovement?->payment_request, 'f10_payment_kind', $f10PaymentKind)
                : null;

            if (is_null($this->sourceRevision)
                && $paymentable
                && TransactionEvent::query()
                    ->where('company_id', $payment->company_id)
                    ->where('client_id', $invoice->client_id)
                    ->where('invoice_id', $invoice->id)
                    ->where('payment_id', $payment->id)
                    ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                    ->where(
                        'payment_request->source_revision',
                        $this->currentSourceRevision($payment, $paymentable),
                    )
                    ->exists()) {
                return;
            }

            if (TransactionEvent::query()
                ->where('company_id', $payment->company_id)
                ->where('client_id', $invoice->client_id)
                ->where('invoice_id', $invoice->id)
                ->where('payment_id', $payment->id)
                ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                ->where('payment_request->operation_key', $operationKey)
                ->exists()) {
                return;
            }

            foreach ($this->targetAllocations(
                $payment,
                $invoice,
                $amount,
                $movementDate,
                $subjectKey,
            ) as $index => $allocation) {
                $movementKey = hash('sha256', json_encode([
                    'operation_key' => $operationKey,
                    'allocation_index' => $index,
                    'period' => $allocation['period'],
                    'amount' => $allocation['amount'],
                ], JSON_THROW_ON_ERROR));

                TransactionEvent::create([
                    'company_id' => $payment->company_id,
                    'client_id' => $invoice->client_id,
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'credit_id' => 0,
                    'event_id' => FranceReportingEventType::PaymentMovement->value,
                    'timestamp' => now()->timestamp,
                    'period' => $allocation['period'],
                    'payment_status' => null,
                    'payment_amount' => $payment->amount ?? 0,
                    'payment_applied' => (float) $allocation['amount'],
                    'payment_refunded' => $payment->refunded ?? 0,
                    'reporting_data' => null,
                    'payment_request' => [
                        'schema_version' => 1,
                        'role' => 'fact',
                        'fact_type' => 'payment_movement',
                        'reporting_path' => $reportingPath,
                        'f10_payment_kind' => $reportingKind,
                        'reporting_profile' => ReportingProfile::Monthly->value,
                        'period_start' => $allocation['period_start'],
                        'subject_key' => $subjectKey,
                        'operation_key' => $operationKey,
                        'movement_key' => $movementKey,
                        'movement_type' => $this->movementType,
                        'movement_amount' => $allocation['amount'],
                        'effective_at' => $movementDate,
                        'report_date' => $allocation['report_date'],
                        'paymentable_id' => $paymentable->id ?? $this->paymentableId,
                        'source_revision' => $this->sourceRevision,
                        'original_document_guid' => $reportingPath === 'payment_received_notification'
                            ? ($this->originalDocumentGuid ?? trim((string) ($invoice->backup->guid ?? '')))
                            : null,
                        'projection_gate' => $projectionGate,
                        'projection_schema_version' => FranceReportMaterializer::PROJECTION_SCHEMA_VERSION,
                    ],
                ]);
            }
        }, attempts: 3);
    }

    /** @return array<int, array{amount: string, period: string, period_start: string, report_date: string}> */
    private function targetAllocations(
        Payment $payment,
        Invoice $invoice,
        string $amount,
        string $movementDate,
        string $subjectKey,
    ): array {
        $currentPeriod = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse($movementDate),
        );

        if (! BcMath::isNegative($amount, 2)
            || $this->movementType === FrancePaymentApplicationRecorder::MOVEMENT_DATE_REVERSED) {
            return [[
                'amount' => $amount,
                'period' => $currentPeriod->end->toDateString(),
                'period_start' => $currentPeriod->start->toDateString(),
                'report_date' => $movementDate,
            ]];
        }

        $periodBalances = [];
        TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('invoice_id', $invoice->id)
            ->where('payment_id', $payment->id)
            ->where('payment_request->subject_key', $subjectKey)
            ->whereNotNull('period')
            ->orderBy('id')
            ->get()
            ->each(function (TransactionEvent $event) use (&$periodBalances, $movementDate): void {
                $period = $event->period->toDateString();
                $balance = $periodBalances[$period] ?? [
                    'amount' => '0',
                    'report_date' => (string) data_get($event->payment_request, 'report_date', $movementDate),
                ];
                $balance['amount'] = BcMath::add(
                    $balance['amount'],
                    data_get($event->payment_request, 'movement_amount', 0),
                    2,
                );
                $periodBalances[$period] = $balance;
            });

        krsort($periodBalances);
        $remaining = BcMath::abs($amount, 2);
        $allocations = [];

        foreach ($periodBalances as $period => $balance) {
            if (! BcMath::greaterThan($balance['amount'], '0', 2)) {
                continue;
            }

            $allocated = BcMath::lessThan($balance['amount'], $remaining, 2)
                ? $balance['amount']
                : $remaining;
            $allocations[] = [
                'amount' => BcMath::sub('0', $allocated, 2),
                'period' => $period,
                'period_start' => ReportingCalendar::currentPeriod(
                    ReportingProfile::Monthly,
                    CarbonImmutable::parse($period),
                )->start->toDateString(),
                'report_date' => $balance['report_date'],
            ];
            $remaining = BcMath::sub($remaining, $allocated, 2);

            if (BcMath::isZero($remaining, 2)) {
                break;
            }
        }

        if (! BcMath::isZero($remaining, 2)) {
            $fallbackDate = $this->paymentable($payment, $invoice)?->created_at
                ? $this->resolveMovementDate($payment, $this->paymentable($payment, $invoice))
                : $movementDate;
            $fallbackDate ??= $movementDate;
            $fallbackPeriod = ReportingCalendar::currentPeriod(
                ReportingProfile::Monthly,
                CarbonImmutable::parse($fallbackDate),
            );
            $allocations[] = [
                'amount' => BcMath::sub('0', $remaining, 2),
                'period' => $fallbackPeriod->end->toDateString(),
                'period_start' => $fallbackPeriod->start->toDateString(),
                'report_date' => $fallbackDate,
            ];
        }

        return $allocations;
    }

    private function paymentable(Payment $payment, Invoice $invoice): ?Paymentable
    {
        $query = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices');

        if ($this->paymentableId) {
            $query->whereKey($this->paymentableId);
        }

        return $query->latest('id')->first();
    }

    private function resolveMovementDate(Payment $payment, ?Paymentable $paymentable): ?string
    {
        if (trim($this->movementDate) !== '') {
            return CarbonImmutable::parse($this->movementDate)->toDateString();
        }

        return $paymentable?->created_at
            ? app(FrancePaymentApplicationDateResolver::class)->resolve(
                $paymentable,
                $payment->company->timezone()?->name ?: config('app.timezone'),
            )
            : null;
    }

    private function reportingPath(Payment $payment): string
    {
        if (($payment->client->classification ?? 'business') === 'individual') {
            return 'f10';
        }

        return $payment->client->country?->iso_3166_2 === 'FR'
            ? 'payment_received_notification'
            : 'f10';
    }

    private function normalizeAmount(int|float|string $amount): string
    {
        return BcMath::round($amount, 2);
    }

    private function currentSourceRevision(Payment $payment, Paymentable $paymentable): string
    {
        $sourceAmount = $paymentable->trashed()
            || $payment->is_deleted
            || in_array((int) $payment->status_id, [
                Payment::STATUS_CANCELLED,
                Payment::STATUS_FAILED,
            ], true)
                ? '0'
                : BcMath::sub($paymentable->amount, $paymentable->refunded ?? 0, 2);

        return hash('sha256', implode('|', [
            (string) $paymentable->updated_at,
            (string) $paymentable->deleted_at,
            (string) $payment->updated_at,
            (string) $payment->status_id,
            (string) (int) $payment->is_deleted,
            $sourceAmount,
        ]));
    }
}
