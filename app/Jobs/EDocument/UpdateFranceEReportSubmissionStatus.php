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
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceSubmissionCallbackStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateFranceEReportSubmissionStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    private const ACCEPTED_EVENTS = ['accepted'];

    private const REJECTED_EVENTS = ['failed', 'rejected', 'no_action_taken', 'not_transportable'];

    private const RETRYABLE_EVENTS = ['retryable_failure', 'temporarily_unavailable'];

    /** @param array<string, mixed> $input */
    public function __construct(private array $input) {}

    public function handle(
        FranceReportMaterializer $materializer,
        FranceSubmissionCallbackStore $callbackStore,
    ): void
    {
        $tenantId = trim((string) ($this->input['tenant_id'] ?? ''));
        $guid = trim((string) ($this->input['guid'] ?? ''));

        if ($tenantId === '' || $guid === '') {
            return;
        }

        if (config('ninja.db.multi_db_enabled') && ! MultiDB::findAndSetDbByCompanyKey($tenantId)) {
            return;
        }

        $company = Company::query()->where('company_key', $tenantId)->first();

        if (! $company) {
            return;
        }

        $submission = TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', \App\Models\Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->whereIn('event_id', FranceReportingEventType::submissionValues())
            ->where(function ($query) use ($guid): void {
                $query->where('payment_request->guid', $guid)
                    ->orWhereJsonContains('payment_request->guids', $guid);
            })
            ->orderByDesc('id')
            ->first();

        if (! $submission) {
            $callbackStore->record($company, $guid, $this->input);

            return;
        }

        $status = $this->statusForEvent((string) ($this->input['event'] ?? ''));

        DB::transaction(function () use ($company, $submission, $status, $materializer): void {
            Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $locked = TransactionEvent::query()->lockForUpdate()->find($submission->id);

            if (! $locked) {
                return;
            }

            $request = $locked->payment_request ?? [];
            $events = is_array($request['events'] ?? null) ? $request['events'] : [];
            $eventKey = hash('sha256', json_encode($this->input, JSON_THROW_ON_ERROR));

            if (! collect($events)->contains(
                static fn(mixed $event): bool => is_array($event) && ($event['event_key'] ?? null) === $eventKey,
            )) {
                $events[] = [
                    'event_key' => $eventKey,
                    'event' => $this->input['event'] ?? null,
                    'event_group' => $this->input['event_group'] ?? null,
                    'received_at' => now()->toIso8601String(),
                    'payload' => $this->input,
                ];
            }
            $request['last_event'] = $this->input['event'] ?? null;
            $request['last_event_group'] = $this->input['event_group'] ?? null;
            $request['events'] = $events;

            if ($this->isTerminal($locked) || is_null($status)) {
                $locked->payment_request = $request;
                $locked->save();

                return;
            }

            $locked->payment_status = $status->value;
            $request[$status === FranceReportingStatus::Accepted ? 'accepted_at' : 'resolved_at'] = now()->toIso8601String();
            $locked->payment_request = $request;
            $locked->save();

            if (! in_array($status, [FranceReportingStatus::Accepted, FranceReportingStatus::Rejected], true)) {
                return;
            }

            $snapshotIds = array_values(array_filter(
                $request['snapshot_event_ids'] ?? [],
                static fn(mixed $id): bool => is_int($id) || ctype_digit((string) $id),
            ));

            TransactionEvent::query()
                ->where('company_id', $locked->company_id)
                ->whereIn('id', $snapshotIds)
                ->whereIn('event_id', [
                    FranceReportingEventType::TransactionSnapshot->value,
                    FranceReportingEventType::PaymentSnapshot->value,
                    FranceReportingEventType::PaymentNotificationSnapshot->value,
                ])
                ->where('payment_request->submission_event_id', $locked->id)
                ->update(['payment_status' => $status->value]);

            if ((int) $locked->event_id === FranceReportingEventType::ReportSubmission->value) {
                $materializer->resolveSubmissionFacts($locked, $status);
            }

            if ((int) $locked->event_id === FranceReportingEventType::PaymentNotificationSubmission->value) {
                TransactionEvent::query()
                    ->where('company_id', $locked->company_id)
                    ->whereIn('id', array_values(array_filter(
                        $request['movement_event_ids'] ?? [$request['movement_event_id'] ?? 0],
                        static fn(mixed $id): bool => is_int($id) || ctype_digit((string) $id),
                    )))
                    ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                    ->eachById(function (TransactionEvent $movement) use ($status): void {
                        $movementRequest = $movement->payment_request ?? [];

                        if (data_get($movementRequest, 'route_transition')
                            === 'notification_reversal_pending_old_outcome') {
                            unset($movementRequest['route_transition']);

                            if ($status === FranceReportingStatus::Accepted) {
                                $movementRequest['local_disposition'] = 'accepted_notification_reversal_mapping_unconfirmed';
                                $movementRequest['projection_gate'] = 'accepted_notification_reversal_mapping_unconfirmed';
                                $movementRequest['projection_schema_version'] = FranceReportMaterializer::PROJECTION_SCHEMA_VERSION;
                            } else {
                                $movementRequest['local_disposition'] = 'notification_not_required_after_rejected_submission';
                                unset($movementRequest['projection_gate']);
                            }

                            $movement->payment_request = $movementRequest;
                            $movement->payment_status = FranceReportingStatus::Accepted->value;
                            $movement->save();

                            return;
                        }

                        if (data_get($movementRequest, 'route_transition')
                            === 'notification_source_invalid_pending_old_outcome') {
                            unset($movementRequest['route_transition']);

                            if ($status === FranceReportingStatus::Accepted) {
                                $movementRequest['local_disposition'] = 'accepted_notification_source_invalid_mapping_unconfirmed';
                                $movementRequest['projection_gate'] = 'accepted_notification_source_invalid_mapping_unconfirmed';
                                $movementRequest['projection_schema_version'] = FranceReportMaterializer::PROJECTION_SCHEMA_VERSION;
                                unset($movementRequest['notification_deferred_at']);
                                unset($movementRequest['notification_deferred_reason']);
                                $movement->payment_status = FranceReportingStatus::Accepted->value;
                            } else {
                                $movementRequest['notification_deferred_at'] = now()->toIso8601String();
                                $movementRequest['notification_deferred_reason'] = 'The previous notification was rejected while the source document was invalid.';
                                unset($movementRequest['projection_gate']);
                                $movement->payment_status = FranceReportingStatus::Pending->value;
                            }

                            $movement->payment_request = $movementRequest;
                            $movement->save();

                            return;
                        }

                        $movedToF10 = data_get(
                            $movementRequest,
                            'current_reporting_path',
                            data_get($movementRequest, 'reporting_path'),
                        ) === 'f10';

                        if (! $movedToF10) {
                            $movement->payment_status = $status->value;
                            $movement->save();

                            return;
                        }

                        if ($status === FranceReportingStatus::Accepted) {
                            $movementRequest['route_transition'] = 'notification_to_f10_mapping_unconfirmed';
                            $movementRequest['projection_gate'] = 'accepted_notification_to_f10_mapping_unconfirmed';
                            $movementRequest['projection_schema_version'] = FranceReportMaterializer::PROJECTION_SCHEMA_VERSION;
                            $movement->payment_status = FranceReportingStatus::Accepted->value;
                        } else {
                            unset($movementRequest['route_transition']);
                            unset($movementRequest['projection_gate']);
                            $movement->payment_status = null;
                        }

                        $movement->payment_request = $movementRequest;
                        $movement->save();
                    });
            }
        }, attempts: 3);
    }

    private function statusForEvent(string $event): ?FranceReportingStatus
    {
        $event = strtolower(trim($event));

        return match (true) {
            in_array($event, self::ACCEPTED_EVENTS, true) => FranceReportingStatus::Accepted,
            in_array($event, self::REJECTED_EVENTS, true) => FranceReportingStatus::Rejected,
            in_array($event, self::RETRYABLE_EVENTS, true) => FranceReportingStatus::RetryableFailure,
            default => null,
        };
    }

    private function isTerminal(TransactionEvent $submission): bool
    {
        return in_array((int) $submission->payment_status, [
            FranceReportingStatus::Accepted->value,
            FranceReportingStatus::Rejected->value,
        ], true);
    }
}
