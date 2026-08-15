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
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Models\Webhook;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateResolver;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
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
use Illuminate\Support\Str;

class RecordFranceEReportingScopeInvalidation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    private const INITIAL_SOURCE_RECONCILIATION_DAYS = 7;

    private string $invalidationKey;

    public function __construct(
        private int $companyId,
        private string $db,
        private ?int $clientId = null,
        ?string $invalidationKey = null,
        private bool $supersedeUnacceptedTransactionScopes = false,
        private bool $initializeCurrentPeriods = false,
        private bool $reconcileRecentSourceState = false,
        private array $clientIds = [],
        private ?int $invalidationEventId = null,
        private ?string $sourceReconciliationSince = null,
        private bool $reconcileHistoricalScopeState = false,
    ) {
        $this->invalidationKey = $invalidationKey ?? Str::uuid()->toString();
    }

    public function handle(): void
    {
        MultiDB::setDb($this->db);

        $company = Company::query()->find($this->companyId);

        if (! $company || ! (bool) $company->getSetting('france_reporting_enabled')) {
            return;
        }

        $supersededFactIds = $this->supersedeUnacceptedTransactionScopes
            ? TransactionEvent::query()
                ->where('company_id', $company->id)
                ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
                ->where('payment_request->family', 'transaction')
                ->whereNull('payment_status')
                ->pluck('id')
            : collect();

        $transactionPeriodStart = ReportingCalendar::currentPeriod(
            ReportingProfile::tryFrom((string) $company->getSetting('france_reporting_schedule'))
                ?? ReportingProfile::TenDay,
            CarbonImmutable::now('Europe/Paris'),
        )->start->toDateString();
        $sourceReconciliationSince = $this->sourceReconciliationSince();
        $scanCurrentPeriods = ! $this->reconcileRecentSourceState
            || $this->initializeCurrentPeriods
            || is_null($this->sourceReconciliationSince);

        foreach ([Invoice::class, Credit::class] as $entity) {
            $documentKey = $entity === Invoice::class ? 'invoice_id' : 'credit_id';
            $snapshotTypes = $entity === Invoice::class
                ? [
                    FranceReportingEventType::TransactionSnapshot->value,
                    FranceReportingEventType::PaymentSnapshot->value,
                    FranceReportingEventType::PaymentMovement->value,
                    FranceReportingEventType::DocumentLifecycle->value,
                ]
                : [
                    FranceReportingEventType::TransactionSnapshot->value,
                    FranceReportingEventType::DocumentLifecycle->value,
                ];

            $entity::withTrashed()
                ->where('company_id', $company->id)
                ->when($this->clientId, fn($query) => $query->where('client_id', $this->clientId))
                ->when($this->clientIds !== [], fn($query) => $query->whereIn('client_id', $this->clientIds))
                ->where(function ($query) use (
                    $company,
                    $documentKey,
                    $snapshotTypes,
                    $transactionPeriodStart,
                    $sourceReconciliationSince,
                    $scanCurrentPeriods,
                ): void {
                    if ($scanCurrentPeriods) {
                        $query->where('date', '>=', $transactionPeriodStart);
                    }

                    if ($this->reconcileRecentSourceState) {
                        $query->orWhere(
                            'updated_at',
                            '>=',
                            $sourceReconciliationSince,
                        );
                        $query->orWhereIn('client_id', Client::withTrashed()
                            ->select('id')
                            ->where('company_id', $company->id)
                            ->where(function ($clientQuery) use ($company, $sourceReconciliationSince): void {
                                $clientQuery
                                    ->where('updated_at', '>=', $sourceReconciliationSince)
                                    ->orWhereIn('group_settings_id', GroupSetting::withTrashed()
                                        ->select('id')
                                        ->where('company_id', $company->id)
                                        ->where('updated_at', '>=', $sourceReconciliationSince));
                            }));
                        $query->orWhereIn('client_id', ClientContact::withTrashed()
                            ->select('client_id')
                            ->where('company_id', $company->id)
                            ->where('updated_at', '>=', $sourceReconciliationSince));
                        $query->orWhereIn('location_id', Location::withTrashed()
                            ->select('id')
                            ->where('company_id', $company->id)
                            ->where('updated_at', '>=', $sourceReconciliationSince));

                        if ($this->reconcileHistoricalScopeState) {
                            $query->orWhereIn('id', TransactionEvent::query()
                                ->select($documentKey)
                                ->where('company_id', $company->id)
                                ->whereIn('event_id', $snapshotTypes));
                        }

                        return;
                    }

                    $query->orWhereIn('id', TransactionEvent::query()
                        ->select($documentKey)
                        ->where('company_id', $company->id)
                        ->whereIn('event_id', $snapshotTypes)
                        ->where($documentKey, '>', 0));
                })
                ->select('id')
                ->chunkById(200, function ($documents) use ($entity, $company): void {
                    foreach ($documents as $document) {
                        RecordFranceEReportingDocumentLifecycle::dispatchSync(
                            $entity,
                            $document->id,
                            0,
                            $company->db,
                            null,
                            $this->invalidationKey,
                        );
                    }
                });
        }

        $supersededFactIds->chunk(500)->each(function ($ids): void {
            TransactionEvent::query()
                ->whereIn('id', $ids->all())
                ->whereNull('payment_status')
                ->update(['payment_status' => FranceReportingStatus::Accepted->value]);
        });

        if ($this->initializeCurrentPeriods || $this->reconcileRecentSourceState) {
            $paymentPeriodStart = CarbonImmutable::now('Europe/Paris')->startOfMonth()->startOfDay();

            Paymentable::withTrashed()
                ->with([
                    'payment' => fn($query) => $query->withTrashed(),
                    'payment.company',
                    'payment.client.country',
                    'payment.client.company',
                    'paymentable.client.country',
                    'paymentable.client.company',
                    'paymentable.company',
                ])
                ->where('paymentable_type', 'invoices')
                ->where(function ($query) use (
                    $paymentPeriodStart,
                    $sourceReconciliationSince,
                    $scanCurrentPeriods,
                ): void {
                    if ($scanCurrentPeriods) {
                        $query->where('created_at', '>=', $paymentPeriodStart);
                    }

                    if ($this->reconcileRecentSourceState) {
                        $query->orWhere(
                            'updated_at',
                            '>=',
                            $sourceReconciliationSince,
                        );
                        $query->orWhereIn('payment_id', Payment::withTrashed()
                            ->select('id')
                            ->where('company_id', $this->companyId)
                            ->where('updated_at', '>=', $sourceReconciliationSince));
                    }
                })
                ->whereIn('payment_id', Payment::withTrashed()
                    ->select('id')
                    ->where('company_id', $company->id)
                    ->when($this->clientId, fn($paymentQuery) => $paymentQuery
                        ->where('client_id', $this->clientId))
                    ->when($this->clientIds !== [], fn($paymentQuery) => $paymentQuery
                        ->whereIn('client_id', $this->clientIds))
                    ->where(function ($paymentQuery): void {
                        $paymentQuery
                            ->whereIn('status_id', [
                                Payment::STATUS_COMPLETED,
                                Payment::STATUS_PARTIALLY_REFUNDED,
                                Payment::STATUS_REFUNDED,
                                Payment::STATUS_CANCELLED,
                                Payment::STATUS_FAILED,
                            ])
                            ->orWhere('is_deleted', true);
                    }))
                ->select([
                    'id',
                    'payment_id',
                    'paymentable_id',
                    'paymentable_type',
                    'amount',
                    'refunded',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ])
                ->chunkById(200, function ($paymentables): void {
                    foreach ($paymentables as $paymentable) {
                        $this->initializePaymentableFacts($paymentable);
                    }
                });
        }

        $this->completeInvalidationEvent();
    }

    private function sourceReconciliationSince(): CarbonImmutable
    {
        if ($this->sourceReconciliationSince) {
            return CarbonImmutable::parse($this->sourceReconciliationSince, 'UTC');
        }

        return CarbonImmutable::now('UTC')->subDays(self::INITIAL_SOURCE_RECONCILIATION_DAYS);
    }

    private function completeInvalidationEvent(): void
    {
        if (! $this->invalidationEventId) {
            return;
        }

        TransactionEvent::query()
            ->whereKey($this->invalidationEventId)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->whereNull('payment_status')
            ->delete();
    }

    private function initializePaymentableFacts(Paymentable $paymentable): void
    {
        /** @var array{0: Payment, 1: Invoice}|null $sourceModels */
        $sourceModels = DB::transaction(function () use ($paymentable): ?array {
            $currentPaymentable = Paymentable::withTrashed()
                ->where('id', $paymentable->id)
                ->first();
            $payment = Payment::withTrashed()
                ->with(['company', 'client.country', 'client.company'])
                ->find($paymentable->payment_id);
            $invoice = Invoice::withTrashed()
                ->with(['client.country', 'client.company', 'company'])
                ->find($paymentable->paymentable_id);

            if (! $currentPaymentable || ! $payment || ! $invoice) {
                return null;
            }

            $this->reconcilePaymentableBalance($currentPaymentable, $payment, $invoice);

            return [$payment, $invoice];
        }, attempts: 3);

        if ($sourceModels) {
            $this->reconcilePaymentProjectionState(...$sourceModels);
        }
    }

    private function reconcilePaymentableBalance(
        Paymentable $paymentable,
        Payment $payment,
        Invoice $invoice,
    ): void {
        $recordedAmount = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('payment_id', $payment->id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('payment_request->paymentable_id', $paymentable->id)
            ->get(['payment_request'])
            ->reduce(
                static fn(string $total, TransactionEvent $event): string => BcMath::add(
                    $total,
                    data_get($event->payment_request, 'movement_amount', 0),
                    2,
                ),
                '0',
            );
        $sourceAmount = $paymentable->trashed()
            || $payment->is_deleted
            || in_array((int) $payment->status_id, [
                Payment::STATUS_CANCELLED,
                Payment::STATUS_FAILED,
            ], true)
                ? '0'
                : BcMath::sub($paymentable->amount, $paymentable->refunded ?? 0, 2);
        $sourceRevision = hash('sha256', implode('|', [
            (string) $paymentable->updated_at,
            (string) $paymentable->deleted_at,
            (string) $payment->updated_at,
            (string) $payment->status_id,
            (string) (int) $payment->is_deleted,
            $sourceAmount,
        ]));

        if (BcMath::equal($recordedAmount, $sourceAmount, 2)) {
            return;
        }

        $timezone = $payment->company->timezone()?->name ?: config('app.timezone');
        $applicationDate = app(FrancePaymentApplicationDateResolver::class)->resolve($paymentable, $timezone);

        if (! $applicationDate) {
            return;
        }

        $reportingPath = ($invoice->client->classification ?? 'business') === 'individual'
            || $invoice->client->country?->iso_3166_2 !== 'FR'
                ? 'f10'
                : 'payment_received_notification';
        $documentGuid = trim((string) ($invoice->backup->guid ?? ''));

        if (BcMath::isZero($recordedAmount, 2)) {
            (new RecordFranceEReportingPayment(
                $payment->id,
                $payment->company->db,
                $invoice->id,
                $paymentable->id,
                (string) $paymentable->amount,
                $applicationDate,
                FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
                "source-reconciliation:{$paymentable->id}:{$sourceRevision}:applied:{$paymentable->amount}",
                $reportingPath,
                $documentGuid,
                $sourceRevision,
            ))->handle();

            if (! BcMath::greaterThan($paymentable->refunded ?? 0, '0', 2)) {
                return;
            }

            $refundDate = app(FrancePaymentApplicationDateResolver::class)->resolveTimestamp(
                $paymentable->updated_at ?: $paymentable->created_at,
                $timezone,
            ) ?? $applicationDate;
            (new RecordFranceEReportingPayment(
                $payment->id,
                $payment->company->db,
                $invoice->id,
                $paymentable->id,
                '-' . BcMath::abs($paymentable->refunded, 2),
                $refundDate,
                FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
                "source-reconciliation:{$paymentable->id}:{$sourceRevision}:refunded:{$paymentable->refunded}",
                $reportingPath,
                $documentGuid,
                $sourceRevision,
            ))->handle();

            return;
        }

        $reconciliationDate = app(FrancePaymentApplicationDateResolver::class)->resolveTimestamp(
            $paymentable->updated_at ?: $paymentable->created_at,
            $timezone,
        ) ?? $applicationDate;
        $movementAmount = BcMath::sub($sourceAmount, $recordedAmount, 2);
        $movementType = $sourceAmount === '0'
            ? FrancePaymentApplicationRecorder::MOVEMENT_DELETED
            : (BcMath::isNegative($movementAmount, 2)
                ? FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED
                : FrancePaymentApplicationRecorder::MOVEMENT_APPLIED);

        (new RecordFranceEReportingPayment(
            $payment->id,
            $payment->company->db,
            $invoice->id,
            $paymentable->id,
            $movementAmount,
            $reconciliationDate,
            $movementType,
            "source-reconciliation:{$paymentable->id}:{$sourceRevision}:{$sourceAmount}",
            $reportingPath,
            $documentGuid,
            $sourceRevision,
        ))->handle();
    }

    private function reconcilePaymentProjectionState(Payment $payment, Invoice $invoice): void
    {
        if (! $this->reconcileRecentSourceState
            || ! $payment->updated_at
            || CarbonImmutable::parse($payment->updated_at)->lessThan($this->sourceReconciliationSince())) {
            return;
        }

        RecordFranceEReportingDocumentLifecycle::dispatchSync(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_PAYMENT,
            $payment->company->db,
            null,
            implode(':', [
                'source-reconciliation-payment',
                $payment->id,
                (string) $payment->updated_at,
                (string) $payment->type_id,
                (string) $payment->status_id,
                (string) (int) $payment->is_deleted,
            ]),
        );
    }
}
