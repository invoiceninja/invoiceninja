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
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Credit;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Append a lightweight scope-invalidating fact; report values are projected later.
 */
class RecordFranceEReportingDocumentLifecycle implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 3;

    public function __construct(
        private string $entity,
        private int $id,
        private int $triggerEventId,
        private string $db,
        private ?string $observedStatus = null,
        private ?string $invalidationKey = null,
    ) {}

    public function handle(): void
    {
        if (! in_array($this->entity, [Invoice::class, Credit::class], true)) {
            return;
        }

        MultiDB::setDb($this->db);

        /** @var Invoice|Credit|null $document */
        $document = $this->entity::withTrashed()
            ->with('company')
            ->find($this->id);

        if (is_null($document)) {
            return;
        }

        $company = $document->getRelation('company');

        if (! $company instanceof Company
            || ! (bool) $company->getSetting('france_reporting_enabled')) {
            return;
        }

        $document->loadMissing(['client.country', 'client.company']);
        $client = $document->getRelation('client');

        if (! $client instanceof Client) {
            return;
        }

        $backupStatus = strtolower(trim((string) ($this->observedStatus ?? $document->backup->e_invoice_status ?? '')));
        $status = $document->is_deleted
            ? 'deleted'
            : ($backupStatus !== '' ? $backupStatus : 'document_changed');
        $projectionGate = match (true) {
            strtoupper((string) $client->currency()?->code) !== 'EUR'
                => 'non_eur_transaction_mapping_unconfirmed',
            $document instanceof Credit
                && ($client->classification ?? 'business') !== 'individual'
                && $client->country?->iso_3166_2 !== 'FR'
                => 'foreign_business_credit_mapping_unconfirmed',
            default => null,
        };
        $profile = ReportingProfile::tryFrom((string) $company->getSetting('france_reporting_schedule'))
            ?? ReportingProfile::TenDay;
        $period = ReportingCalendar::currentPeriod(
            $profile,
            CarbonImmutable::parse($document->date ?: now()->toDateString()),
        );
        $scopes = [];

        $snapshotQuery = TransactionEvent::query()
            ->where('company_id', $document->company_id)
            ->where(function ($query) use ($document): void {
                $document instanceof Invoice
                    ? $query->where('invoice_id', $document->id)
                    : $query->where('credit_id', $document->id);
            });

        $transactionSnapshots = (clone $snapshotQuery)
            ->where('event_id', FranceReportingEventType::TransactionSnapshot->value)
            ->whereIn('payment_status', [
                ...FranceReportingStatus::openValues(),
                FranceReportingStatus::Accepted->value,
            ])
            ->get(['period', 'payment_request']);
        $currentDateBelongsToAcceptedScope = false;

        foreach ($transactionSnapshots as $snapshot) {
            $periodEnd = $snapshot->period->toDateString();
            $snapshotProfile = ReportingProfile::tryFrom(
                (string) data_get($snapshot->payment_request, 'reporting_profile'),
            ) ?? $profile;
            $acceptedPeriodForCurrentDate = ReportingCalendar::currentPeriod(
                $snapshotProfile,
                CarbonImmutable::parse($document->date ?: now()->toDateString()),
            );
            $snapshotPeriod = ReportingCalendar::currentPeriod(
                $snapshotProfile,
                CarbonImmutable::parse($periodEnd),
            );
            $currentDateBelongsToAcceptedScope = $currentDateBelongsToAcceptedScope
                || $acceptedPeriodForCurrentDate->end->toDateString() === $periodEnd;
            $scopes[implode(':', [
                'transaction',
                $snapshotProfile->value,
                $snapshotPeriod->start->toDateString(),
                $periodEnd,
            ])] = [
                'family' => 'transaction',
                'period' => $periodEnd,
                'period_start' => $snapshotPeriod->start->toDateString(),
                'profile' => $snapshotProfile->value,
            ];
        }

        if ($transactionSnapshots->isEmpty() || ! $currentDateBelongsToAcceptedScope) {
            $scopes[implode(':', [
                'transaction',
                $profile->value,
                $period->start->toDateString(),
                $period->end->toDateString(),
            ])] = [
                'family' => 'transaction',
                'period' => $period->end->toDateString(),
                'period_start' => $period->start->toDateString(),
                'profile' => $profile->value,
            ];
        }

        if ($document instanceof Invoice) {
            $this->reopenDeferredPaymentNotifications($document);

            $paymentPeriods = TransactionEvent::query()
                ->where('company_id', $document->company_id)
                ->where('invoice_id', $document->id)
                ->where(function ($query): void {
                    $query->where(function ($movementQuery): void {
                        $movementQuery
                            ->where('event_id', FranceReportingEventType::PaymentMovement->value);
                    })->orWhere(function ($snapshotQuery): void {
                        $snapshotQuery
                            ->where('event_id', FranceReportingEventType::PaymentSnapshot->value)
                            ->where('payment_status', FranceReportingStatus::Accepted->value);
                    });
                })
                ->whereNotNull('period')
                ->distinct()
                ->pluck('period');

            foreach ($paymentPeriods as $paymentPeriod) {
                $periodEnd = CarbonImmutable::parse((string) $paymentPeriod)->toDateString();
                $paymentPeriod = ReportingCalendar::currentPeriod(
                    ReportingProfile::Monthly,
                    CarbonImmutable::parse($periodEnd),
                );
                $scopes[implode(':', [
                    'payment',
                    ReportingProfile::Monthly->value,
                    $paymentPeriod->start->toDateString(),
                    $periodEnd,
                ])] = [
                    'family' => 'payment',
                    'period' => $periodEnd,
                    'period_start' => $paymentPeriod->start->toDateString(),
                    'profile' => ReportingProfile::Monthly->value,
                ];
            }
        }

        $groupSettingsRevision = $client->group_settings_id
            ? GroupSetting::withTrashed()
                ->whereKey($client->group_settings_id)
                ->first(['id', 'updated_at', 'deleted_at'])
                ?->getAttributes()
            : null;
        $contactRevisions = ClientContact::withTrashed()
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->get(['id', 'updated_at', 'deleted_at'])
            ->map(static fn(ClientContact $contact): array => $contact->getAttributes())
            ->all();
        $locationRevision = $document->location
            ? $document->location->only(['id', 'updated_at', 'deleted_at'])
            : null;
        $sourceStateHash = hash('sha256', json_encode([
            'client_currency' => strtoupper((string) $client->currency()?->code),
            'client_name' => $client->present()->name(),
            'client_email' => $client->present()->email(),
            'client_e_invoice' => $client->e_invoice,
            'location' => $document->service()->location(false),
            'group_settings_revision' => $groupSettingsRevision,
            'contact_revisions' => $contactRevisions,
            'location_revision' => $locationRevision,
            'company_context' => app(FranceReportMaterializer::class)->reportingContextHash($company),
        ], JSON_THROW_ON_ERROR));

        foreach ($scopes as $scope) {
            $eventKey = hash('sha256', json_encode([
                'entity' => $this->entity,
                'id' => $document->id,
                'trigger_event_id' => $this->triggerEventId,
                'updated_at' => (string) $document->updated_at,
                'client_updated_at' => (string) $client->updated_at,
                'company_updated_at' => (string) $company->updated_at,
                'is_deleted' => (bool) $document->is_deleted,
                'status_id' => (int) $document->status_id,
                'status' => $status,
                'family' => $scope['family'],
                'period_start' => $scope['period_start'],
                'period' => $scope['period'],
                'invalidation_key' => $this->invalidationKey,
                'source_state_hash' => $sourceStateHash,
            ], JSON_THROW_ON_ERROR));

            if (TransactionEvent::query()
                ->where('company_id', $document->company_id)
                ->where('client_id', $document->client_id)
                ->where($document instanceof Invoice ? 'invoice_id' : 'credit_id', $document->id)
                ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
                ->where('period', $scope['period'])
                ->where('payment_request->event_key', $eventKey)
                ->exists()) {
                continue;
            }

            TransactionEvent::create([
                'company_id' => $document->company_id,
                'client_id' => $document->client_id,
                'invoice_id' => $document instanceof Invoice ? $document->id : 0,
                'payment_id' => 0,
                'credit_id' => $document instanceof Credit ? $document->id : 0,
                'event_id' => FranceReportingEventType::DocumentLifecycle->value,
                'timestamp' => now()->timestamp,
                'period' => $scope['period'],
                'payment_status' => null,
                'reporting_data' => null,
                'payment_request' => [
                    'schema_version' => 1,
                    'role' => 'fact',
                    'fact_type' => 'document_lifecycle',
                    'event_key' => $eventKey,
                    'trigger_event_id' => $this->triggerEventId,
                    'family' => $scope['family'],
                    'reporting_profile' => $scope['profile'],
                    'period_start' => $scope['period_start'],
                    'status' => $status,
                    'projection_gate' => $projectionGate,
                    'projection_schema_version' => FranceReportMaterializer::PROJECTION_SCHEMA_VERSION,
                    'invalidation_key' => $this->invalidationKey,
                    'observed_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }

    private function reopenDeferredPaymentNotifications(Invoice $invoice): void
    {
        $status = strtolower(trim((string) ($invoice->backup->e_invoice_status ?? '')));
        $guid = trim((string) ($invoice->backup->guid ?? ''));

        if (((int) $invoice->status_id !== Invoice::STATUS_PAID
                && (float) $invoice->balance > 0)
            || $status === 'rejected'
            || ($status !== 'cleared' && is_null($invoice->backup->e_invoice_cleared_at))
            || $guid === '') {
            return;
        }

        DB::transaction(function () use ($invoice, $guid): void {
            TransactionEvent::query()
                ->where('company_id', $invoice->company_id)
                ->where('invoice_id', $invoice->id)
                ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                ->where('payment_request->reporting_path', 'payment_received_notification')
                ->where('payment_status', FranceReportingStatus::Pending->value)
                ->whereNotNull('payment_request->notification_deferred_at')
                ->eachById(function (TransactionEvent $movement) use ($guid): void {
                    $request = $movement->payment_request ?? [];
                    $request['original_document_guid'] = $guid;
                    unset($request['notification_deferred_at'], $request['notification_deferred_reason']);
                    $movement->payment_request = $request;
                    $movement->payment_status = null;
                    $movement->save();
                });

            TransactionEvent::query()
                ->where('company_id', $invoice->company_id)
                ->where('invoice_id', $invoice->id)
                ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
                ->where('payment_status', FranceReportingStatus::Pending->value)
                ->whereNotNull('payment_request->deferred_at')
                ->eachById(function (TransactionEvent $submission) use ($guid): void {
                    $request = $submission->payment_request ?? [];

                    if (data_get($request, 'attempts', []) !== []) {
                        return;
                    }

                    if (trim((string) data_get($request, 'original_document_guid')) !== $guid) {
                        $request['local_disposition'] = 'document_guid_changed_before_transport';
                        $request['superseded_at'] = now()->toIso8601String();
                        $submission->payment_request = $request;
                        $submission->payment_status = FranceReportingStatus::Rejected->value;
                        $submission->save();

                        TransactionEvent::query()
                            ->where('company_id', $submission->company_id)
                            ->whereIn('id', array_map('intval', $request['snapshot_event_ids'] ?? []))
                            ->update(['payment_status' => FranceReportingStatus::Rejected->value]);
                        TransactionEvent::query()
                            ->where('company_id', $submission->company_id)
                            ->whereIn('id', array_map(
                                'intval',
                                $request['movement_event_ids'] ?? [$request['movement_event_id'] ?? 0],
                            ))
                            ->update(['payment_status' => null]);

                        return;
                    }

                    unset($request['deferred_at'], $request['deferred_reason']);
                    $submission->payment_request = $request;
                    $submission->save();
                });
        }, attempts: 3);
    }

}
