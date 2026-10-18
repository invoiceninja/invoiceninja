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

use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Utils\BcMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

final class FrancePaymentNotificationProcessor
{
    private const MAX_TRANSPORT_ATTEMPTS = 6;

    public function __construct(
        private readonly Storecove $storecove,
        private readonly FranceSubmissionCallbackStore $callbackStore,
    ) {}

    public function process(
        int $transactionEventId,
        string $db,
        bool $force = false,
    ): void {
        MultiDB::setDb($db);

        $event = TransactionEvent::query()->find($transactionEventId);

        if (! $event) {
            return;
        }

        /** @var Company|null $company */
        $company = Company::query()->find($event->company_id);

        if (! $company
            || ! (bool) $company->getSetting('france_reporting_enabled')) {
            return;
        }

        $submission = (int) $event->event_id === FranceReportingEventType::PaymentMovement->value
            ? $this->materialize($event)
            : $event;

        if (! $submission
            || (int) $submission->event_id !== FranceReportingEventType::PaymentNotificationSubmission->value
            || ! in_array((int) $submission->payment_status, [
                FranceReportingStatus::Pending->value,
                FranceReportingStatus::RetryableFailure->value,
            ], true)) {
            return;
        }

        $company->loadMissing('account');
        $account = $company->getRelation('account');

        if ($company->is_disabled
            || ! $account instanceof Account
            || $account->is_flagged) {
            return;
        }

        if (! $force
            && count((array) data_get($submission->payment_request, 'attempts', [])) >= self::MAX_TRANSPORT_ATTEMPTS) {
            $this->recordRetryExhaustion(
                $submission,
                'France payment notification transport retry limit reached.',
            );

            return;
        }

        $payload = data_get($submission->payment_request, 'payload');
        $payloadHash = (string) data_get($submission->payment_request, 'payload_hash');

        if (! is_array($payload)
            || ! hash_equals($payloadHash, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)))) {
            $this->recordLocalTerminalFailure(
                $submission,
                'Stored France payment notification payload is missing or has changed.',
            );

            return;
        }

        if (data_get($submission->payment_request, 'attempts', []) === []
            && is_null(data_get($submission->payment_request, 'transport_claimed_at'))
            && ! $this->preflightSubmission($submission)) {
            return;
        }

        if (! $this->claimTransport($submission)) {
            return;
        }

        try {
            $response = $this->storecove->proxy
                ->setCompany($company)
                ->submitDocument([
                    ...$payload,
                    'tenant_id' => $company->company_key,
                    'account_key' => $account->key,
                    'e_invoicing_token' => $account->e_invoicing_token,
                ]);
            $guid = $response['guid'] ?? null;

            if (! is_string($guid) || trim($guid) === '') {
                throw new RuntimeException('Storecove payment notification response did not contain a GUID.');
            }

            $this->recordAttempt($submission, FranceReportingStatus::Sent, $response, null, $guid);
            $this->callbackStore->replay($company, $guid);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordAttempt($submission, FranceReportingStatus::RetryableFailure, [], [
                'message' => $exception->getMessage(),
                'class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function materialize(TransactionEvent $movement): ?TransactionEvent
    {
        if (in_array((int) $movement->payment_status, [
            FranceReportingStatus::Accepted->value,
            FranceReportingStatus::Rejected->value,
        ], true)) {
            return null;
        }

        $paymentableId = (int) data_get($movement->payment_request, 'paymentable_id', 0);
        $effectiveAt = trim((string) data_get($movement->payment_request, 'effective_at'));
        $invoice = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->find($movement->invoice_id);
        $payment = Payment::withTrashed()->find($movement->payment_id);
        $paymentable = $paymentableId > 0
            ? Paymentable::withTrashed()->find($paymentableId)
            : null;
        $currentGuid = trim((string) ($invoice?->backup->guid ?? ''));
        $currentStatus = strtolower(trim((string) ($invoice?->backup->e_invoice_status ?? '')));
        $storedPath = (string) data_get($movement->payment_request, 'reporting_path');
        $parisToday = CarbonImmutable::now('Europe/Paris')->startOfDay();

        if ($storedPath !== 'payment_received_notification') {
            return null;
        }

        if (BcMath::lessThanOrEqual(data_get($movement->payment_request, 'movement_amount', 0), '0', 2)) {
            $this->resolveNonPositiveMovement($movement);

            return null;
        }

        if (! $invoice
            || ! $payment
            || ! $paymentable
            || $payment->is_deleted
            || (int) $payment->status_id !== Payment::STATUS_COMPLETED
            || $invoice->is_deleted
            || $paymentable->trashed()
            || (int) $paymentable->payment_id !== (int) $payment->id
            || (int) $paymentable->paymentable_id !== (int) $invoice->id
            || $paymentable->paymentable_type !== 'invoices'
            || (int) $movement->company_id !== (int) $payment->company_id
            || (int) $movement->company_id !== (int) $invoice->company_id
            || (int) $movement->client_id !== (int) $payment->client_id
            || (int) $movement->client_id !== (int) $invoice->client_id
        ) {
            $this->rejectMovement($movement);

            return null;
        }

        if ($currentStatus === 'rejected') {
            $this->deferMovement($movement, 'The source invoice is awaiting a cleared recovery state.');

            return null;
        }

        if (((int) $invoice->status_id !== Invoice::STATUS_PAID
                && ! BcMath::lessThanOrEqual($invoice->balance ?? 0, '0', 2))
            || ($currentStatus !== 'cleared' && is_null($invoice->backup->e_invoice_cleared_at))
            || $currentGuid === ''
            || ($effectiveAt !== ''
                && CarbonImmutable::parse($effectiveAt, 'Europe/Paris')->startOfDay()->greaterThan($parisToday))) {
            if ($effectiveAt === ''
                || ! CarbonImmutable::parse($effectiveAt, 'Europe/Paris')->startOfDay()->greaterThan($parisToday)) {
                $this->deferMovement($movement, 'The source invoice is not yet eligible for notification.');
            }

            return null;
        }

        $originalGuid = trim((string) data_get(
            $movement->payment_request,
            'original_document_guid',
        )) ?: $currentGuid;

        $idempotencyGuid = Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "fr-payment-notification|{$movement->company_id}|{$invoice->id}|{$originalGuid}|{$movement->id}",
        )->toString();
        $payload = [
            'forDocumentSubmissionGuid' => $originalGuid,
            'idempotencyGuid' => $idempotencyGuid,
            'document' => [
                'documentType' => 'payment_received_notification',
                'paymentReceivedNotification' => ['mode' => 'auto'],
            ],
        ];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $movement,
            $invoice,
            $originalGuid,
            $idempotencyGuid,
            $payload,
            $payloadHash,
            $parisToday,
        ): ?TransactionEvent {
            $movementIds = TransactionEvent::query()
                ->where('company_id', $movement->company_id)
                ->where('invoice_id', $invoice->id)
                ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                ->where('payment_request->reporting_path', 'payment_received_notification')
                ->where('payment_request->original_document_guid', $originalGuid)
                ->where(function ($query): void {
                    $query->whereNull('payment_status')
                        ->orWhere('payment_status', FranceReportingStatus::Pending->value);
                })
                ->get(['id', 'payment_request'])
                ->filter(static function (TransactionEvent $event) use ($parisToday): bool {
                    $candidateEffectiveAt = trim((string) data_get(
                        $event->payment_request,
                        'effective_at',
                    ));

                    return BcMath::greaterThan(
                        data_get($event->payment_request, 'movement_amount', 0),
                        '0',
                        2,
                    ) && ($candidateEffectiveAt === ''
                        || ! CarbonImmutable::parse($candidateEffectiveAt, 'Europe/Paris')
                            ->startOfDay()
                            ->greaterThan($parisToday));
                })
                ->pluck('id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->push((int) $movement->id)
                ->unique()
                ->values()
                ->all();
            $existing = TransactionEvent::query()
                ->where('company_id', $movement->company_id)
                ->where('invoice_id', $invoice->id)
                ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
                ->where('payment_request->original_document_guid', $originalGuid)
                ->whereNull('payment_request->local_disposition')
                ->whereIn('payment_status', [
                    ...FranceReportingStatus::openValues(),
                    FranceReportingStatus::Accepted->value,
                ])
                ->latest('id')
                ->first();

            if ($existing) {
                $request = $existing->payment_request ?? [];

                if ((int) $existing->payment_status === FranceReportingStatus::Accepted->value) {
                    TransactionEvent::query()
                        ->where('company_id', $movement->company_id)
                        ->whereIn('id', $movementIds)
                        ->update(['payment_status' => FranceReportingStatus::Accepted->value]);

                    return null;
                }

                if ((int) $existing->payment_status === FranceReportingStatus::Sent->value
                    || data_get($request, 'attempts', []) !== []
                    || ! is_null(data_get($request, 'transport_claimed_at'))) {
                    return null;
                }

                $movementIds = array_values(array_unique([
                    ...array_map('intval', $request['movement_event_ids'] ?? []),
                    ...$movementIds,
                ]));
                $request['movement_event_ids'] = $movementIds;
                $request['movement_event_id'] = $movementIds[0];
                $existing->payment_request = $request;
                $existing->save();
                TransactionEvent::query()
                    ->where('company_id', $movement->company_id)
                    ->whereIn('id', $movementIds)
                    ->update(['payment_status' => (int) $existing->payment_status]);

                return in_array((int) $existing->payment_status, [
                    FranceReportingStatus::Pending->value,
                    FranceReportingStatus::RetryableFailure->value,
                ], true)
                    ? $existing
                    : null;
            }

            $submission = TransactionEvent::create([
                'company_id' => $movement->company_id,
                'client_id' => $movement->client_id,
                'invoice_id' => $movement->invoice_id,
                'payment_id' => $movement->payment_id,
                'credit_id' => 0,
                'event_id' => FranceReportingEventType::PaymentNotificationSubmission->value,
                'timestamp' => now()->timestamp,
                'period' => $movement->period,
                'payment_status' => FranceReportingStatus::Pending->value,
                'reporting_data' => null,
                'payment_request' => [
                    'schema_version' => 1,
                    'role' => 'submission',
                    'movement_event_id' => $movementIds[0],
                    'movement_event_ids' => $movementIds,
                    'original_document_guid' => $originalGuid,
                    'idempotency_guid' => $idempotencyGuid,
                    'payload' => $payload,
                    'payload_hash' => $payloadHash,
                    'snapshot_event_ids' => [],
                    'attempts' => [],
                ],
            ]);
            $snapshot = TransactionEvent::create([
                'company_id' => $movement->company_id,
                'client_id' => $movement->client_id,
                'invoice_id' => $movement->invoice_id,
                'payment_id' => $movement->payment_id,
                'credit_id' => 0,
                'event_id' => FranceReportingEventType::PaymentNotificationSnapshot->value,
                'timestamp' => now()->timestamp,
                'period' => $movement->period,
                'payment_status' => FranceReportingStatus::Pending->value,
                'reporting_data' => null,
                'payment_request' => [
                    'schema_version' => 1,
                    'role' => 'projection_snapshot',
                    'subject_key' => "invoice:{$invoice->id}",
                    'submission_event_id' => $submission->id,
                ],
            ]);
            $request = $submission->payment_request;
            $request['snapshot_event_ids'] = [$snapshot->id];
            $submission->payment_request = $request;
            $submission->save();
            TransactionEvent::query()
                ->where('company_id', $movement->company_id)
                ->whereIn('id', $movementIds)
                ->update(['payment_status' => FranceReportingStatus::Pending->value]);

            return $submission;
        }, attempts: 3);
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, string>|null $error
     */
    private function recordAttempt(
        TransactionEvent $submission,
        FranceReportingStatus $status,
        array $response,
        ?array $error,
        ?string $guid = null,
        ?string $localDisposition = null,
    ): void {
        DB::transaction(function () use ($submission, $status, $response, $error, $guid, $localDisposition): void {
            $locked = TransactionEvent::query()->lockForUpdate()->find($submission->id);

            if (! $locked
                || (int) $locked->event_id !== FranceReportingEventType::PaymentNotificationSubmission->value
                || in_array((int) $locked->payment_status, [
                    FranceReportingStatus::Accepted->value,
                    FranceReportingStatus::Rejected->value,
                ], true)) {
                return;
            }

            $request = $locked->payment_request ?? [];
            $attempts = $request['attempts'] ?? [];
            $hasTransportCommitment = $attempts !== []
                || ! is_null(data_get($request, 'transport_claimed_at'));

            if ($localDisposition === 'invalid_persisted_payload' && $hasTransportCommitment) {
                $request['last_error'] = $error;
                $request['local_disposition'] = 'invalid_persisted_payload_after_transport_commitment';
                $request['retry_exhausted_at'] ??= now()->toIso8601String();
                $locked->payment_request = $request;
                $locked->payment_status = FranceReportingStatus::RetryableFailure->value;
                $locked->save();

                return;
            }

            $attempts[] = [
                'attempted_at' => now()->toIso8601String(),
                'response' => $response,
                'error' => $error,
            ];
            $request['attempts'] = $attempts;
            $knownGuids = array_values(array_unique(array_filter([
                ...array_map('strval', (array) ($request['guids'] ?? [])),
                trim((string) ($request['guid'] ?? '')),
                trim((string) ($guid ?? '')),
            ])));
            $request['guid'] = trim((string) ($request['guid'] ?? '')) ?: ($guid ?? null);
            $request['guids'] = $knownGuids;
            $request['sent_at'] = $status === FranceReportingStatus::Sent
                ? now()->toIso8601String()
                : ($request['sent_at'] ?? null);
            $request['last_error'] = $error;
            $request['local_disposition'] = $localDisposition ?? ($request['local_disposition'] ?? null);
            unset($request['retry_exhausted_at']);
            unset($request['transport_claimed_at']);
            $locked->payment_request = $request;
            $locked->payment_status = $status->value;
            $locked->save();

            if ($status === FranceReportingStatus::Rejected) {
                TransactionEvent::query()
                    ->where('company_id', $locked->company_id)
                    ->whereIn('id', array_values(array_filter(
                        $request['snapshot_event_ids'] ?? [],
                        static fn(mixed $id): bool => is_int($id) || ctype_digit((string) $id),
                    )))
                    ->where('event_id', FranceReportingEventType::PaymentNotificationSnapshot->value)
                    ->where('payment_request->submission_event_id', $locked->id)
                    ->update(['payment_status' => FranceReportingStatus::Rejected->value]);

                TransactionEvent::query()
                    ->where('company_id', $locked->company_id)
                    ->whereIn('id', array_values(array_filter(
                        $request['movement_event_ids'] ?? [$request['movement_event_id'] ?? 0],
                        static fn(mixed $id): bool => is_int($id) || ctype_digit((string) $id),
                    )))
                    ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                    ->update(['payment_status' => FranceReportingStatus::Rejected->value]);

                if ($localDisposition === 'invalid_persisted_payload') {
                    TransactionEvent::query()
                        ->where('company_id', $locked->company_id)
                        ->whereIn('id', array_values(array_filter(
                            $request['movement_event_ids'] ?? [$request['movement_event_id'] ?? 0],
                            static fn(mixed $id): bool => is_int($id) || ctype_digit((string) $id),
                        )))
                        ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                        ->update(['payment_status' => null]);
                }
            }
        }, attempts: 3);
    }

    private function recordLocalTerminalFailure(TransactionEvent $submission, string $message): void
    {
        $localDisposition = 'invalid_persisted_payload';
        $this->recordAttempt(
            $submission,
            FranceReportingStatus::Rejected,
            [],
            ['message' => $message, 'class' => RuntimeException::class],
            null,
            $localDisposition,
        );

    }

    private function recordRetryExhaustion(TransactionEvent $submission, string $message): void
    {
        DB::transaction(function () use ($submission, $message): void {
            $locked = TransactionEvent::query()->lockForUpdate()->find($submission->id);

            if (! $locked
                || (int) $locked->event_id !== FranceReportingEventType::PaymentNotificationSubmission->value
                || in_array((int) $locked->payment_status, [
                    FranceReportingStatus::Accepted->value,
                    FranceReportingStatus::Rejected->value,
                ], true)) {
                return;
            }

            $request = $locked->payment_request ?? [];
            $request['retry_exhausted_at'] ??= now()->toIso8601String();
            $request['last_error'] = [
                'message' => $message,
                'class' => RuntimeException::class,
            ];
            unset($request['transport_claimed_at']);
            $locked->payment_request = $request;
            $locked->payment_status = FranceReportingStatus::RetryableFailure->value;
            $locked->save();
        }, attempts: 3);
    }

    private function deferSubmission(TransactionEvent $submission, string $message): void
    {
        DB::transaction(function () use ($submission, $message): void {
            $locked = TransactionEvent::query()->lockForUpdate()->find($submission->id);

            if (! $locked
                || (int) $locked->event_id !== FranceReportingEventType::PaymentNotificationSubmission->value
                || data_get($locked->payment_request, 'attempts', []) !== []) {
                return;
            }

            $request = $locked->payment_request ?? [];
            $request['deferred_reason'] = $message;
            $request['deferred_at'] = now()->toIso8601String();
            unset($request['transport_claimed_at']);
            $locked->payment_request = $request;
            $locked->payment_status = FranceReportingStatus::Pending->value;
            $locked->save();
        }, attempts: 3);
    }

    private function deferMovement(TransactionEvent $movement, string $message): void
    {
        $request = $movement->payment_request ?? [];
        $request['notification_deferred_at'] = now()->toIso8601String();
        $request['notification_deferred_reason'] = $message;
        $movement->payment_request = $request;
        $movement->payment_status = FranceReportingStatus::Pending->value;
        $movement->save();
    }

    private function preflightSubmission(TransactionEvent $submission): bool
    {
        $request = $submission->payment_request ?? [];
        $movementIds = array_values(array_filter(
            array_map('intval', $request['movement_event_ids'] ?? [$request['movement_event_id'] ?? 0]),
            static fn(int $id): bool => $id > 0,
        ));
        $movements = TransactionEvent::query()
            ->where('company_id', $submission->company_id)
            ->whereIn('id', $movementIds)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->get();
        $invoice = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->find($submission->invoice_id);

        if (! $invoice
            || $movements->count() !== count($movementIds)
            || $invoice->is_deleted
            || in_array((int) $invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED], true)) {
            $this->supersedeSubmission($submission, 'source_no_longer_notification_eligible');

            return false;
        }

        $hasEligibleSourceMovement = false;

        foreach ($movements as $movement) {
            if (data_get($movement->payment_request, 'reporting_path') !== 'payment_received_notification') {
                $this->supersedeSubmission($submission, 'source_no_longer_notification_eligible');

                return false;
            }

            $paymentableId = (int) data_get($movement->payment_request, 'paymentable_id');
            $payment = Payment::withTrashed()->find($movement->payment_id);
            $paymentable = Paymentable::withTrashed()->find($paymentableId);
            $hasEligibleSourceMovement = $hasEligibleSourceMovement
                || ($payment
                    && $paymentable
                    && ! $payment->is_deleted
                    && (int) $payment->status_id === Payment::STATUS_COMPLETED
                    && ! $paymentable->trashed()
                    && (int) $paymentable->payment_id === (int) $payment->id
                    && (int) $paymentable->paymentable_id === (int) $invoice->id);
        }

        if (! $hasEligibleSourceMovement) {
            $this->supersedeSubmission($submission, 'source_no_longer_notification_eligible');

            return false;
        }

        $currentGuid = trim((string) ($invoice->backup->guid ?? ''));
        $currentStatus = strtolower(trim((string) ($invoice->backup->e_invoice_status ?? '')));
        $originalGuid = trim((string) data_get($request, 'original_document_guid'));

        if ($currentGuid !== '' && $originalGuid !== '' && $originalGuid !== $currentGuid) {
            $this->supersedeSubmission($submission, 'document_guid_changed_before_transport', true);

            return false;
        }

        if ($currentStatus === 'rejected'
            || ((int) $invoice->status_id !== Invoice::STATUS_PAID
                && ! BcMath::lessThanOrEqual($invoice->balance ?? 0, '0', 2))
            || ($currentStatus !== 'cleared' && is_null($invoice->backup->e_invoice_cleared_at))
            || $currentGuid === '') {
            $this->deferSubmission($submission, 'The source invoice is awaiting notification eligibility.');

            return false;
        }

        return true;
    }

    private function supersedeSubmission(
        TransactionEvent $submission,
        string $disposition,
        bool $reopenMovements = false,
    ): void {
        DB::transaction(function () use ($submission, $disposition, $reopenMovements): void {
            $locked = TransactionEvent::query()->lockForUpdate()->find($submission->id);

            if (! $locked || data_get($locked->payment_request, 'attempts', []) !== []) {
                return;
            }

            $request = $locked->payment_request ?? [];
            $request['local_disposition'] = $disposition;
            $request['superseded_at'] = now()->toIso8601String();
            unset($request['transport_claimed_at']);
            $locked->payment_request = $request;
            $locked->payment_status = FranceReportingStatus::Rejected->value;
            $locked->save();

            TransactionEvent::query()
                ->where('company_id', $locked->company_id)
                ->whereIn('id', array_map('intval', $request['snapshot_event_ids'] ?? []))
                ->update(['payment_status' => FranceReportingStatus::Rejected->value]);
            TransactionEvent::query()
                ->where('company_id', $locked->company_id)
                ->whereIn('id', array_map(
                    'intval',
                    $request['movement_event_ids'] ?? [$request['movement_event_id'] ?? 0],
                ))
                ->update([
                    'payment_status' => $reopenMovements
                        ? null
                        : FranceReportingStatus::Accepted->value,
                ]);
        }, attempts: 3);
    }

    private function resolveNonPositiveMovement(TransactionEvent $movement): void
    {
        DB::transaction(function () use ($movement): void {
            $lockedMovement = TransactionEvent::query()->lockForUpdate()->findOrFail($movement->id);
            $request = $lockedMovement->payment_request ?? [];
            $invoice = Invoice::withTrashed()->find($lockedMovement->invoice_id);
            $invoiceRemainsPaid = $invoice
                && ! $invoice->is_deleted
                && ! in_array((int) $invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED], true)
                && ((int) $invoice->status_id === Invoice::STATUS_PAID
                    || BcMath::lessThanOrEqual($invoice->balance ?? 0, '0', 2));
            $requiresReview = ! $invoiceRemainsPaid && TransactionEvent::query()
                ->where('company_id', $lockedMovement->company_id)
                ->where('invoice_id', $lockedMovement->invoice_id)
                ->where('event_id', FranceReportingEventType::PaymentNotificationSnapshot->value)
                ->where('payment_status', FranceReportingStatus::Accepted->value)
                ->exists();

            $candidateIds = TransactionEvent::query()
                ->where('company_id', $lockedMovement->company_id)
                ->where('invoice_id', $lockedMovement->invoice_id)
                ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
                ->whereIn('payment_status', FranceReportingStatus::openValues())
                ->orderBy('id')
                ->pluck('id');

            foreach ($candidateIds as $candidateId) {
                $submission = TransactionEvent::query()->lockForUpdate()->find($candidateId);

                if (! $submission
                    || ! in_array((int) $submission->payment_status, FranceReportingStatus::openValues(), true)) {
                    continue;
                }

                if ($invoiceRemainsPaid) {
                    continue;
                }

                $submissionRequest = $submission->payment_request ?? [];
                $hasTransportCommitment = (int) $submission->payment_status !== FranceReportingStatus::Pending->value
                    || data_get($submissionRequest, 'attempts', []) !== []
                    || ! is_null(data_get($submissionRequest, 'transport_claimed_at'));

                if ($hasTransportCommitment) {
                    $requiresReview = true;
                    continue;
                }

                $submissionRequest['local_disposition'] = 'payment_no_longer_eligible_before_transport';
                $submissionRequest['superseded_at'] = now()->toIso8601String();
                $submission->payment_request = $submissionRequest;
                $submission->payment_status = FranceReportingStatus::Rejected->value;
                $submission->save();
                TransactionEvent::query()
                    ->where('company_id', $submission->company_id)
                    ->whereIn('id', array_map('intval', $submissionRequest['snapshot_event_ids'] ?? []))
                    ->update(['payment_status' => FranceReportingStatus::Rejected->value]);
                TransactionEvent::query()
                    ->where('company_id', $submission->company_id)
                    ->whereIn('id', array_map(
                        'intval',
                        $submissionRequest['movement_event_ids']
                            ?? [$submissionRequest['movement_event_id'] ?? 0],
                    ))
                    ->update(['payment_status' => FranceReportingStatus::Accepted->value]);
            }

            $request['local_disposition'] = $requiresReview
                ? 'notification_adjustment_unreported'
                : 'notification_not_required_for_non_positive_movement';
            $lockedMovement->payment_request = $request;
            $lockedMovement->payment_status = FranceReportingStatus::Accepted->value;
            $lockedMovement->save();
        }, attempts: 3);
    }

    private function rejectMovement(TransactionEvent $movement): void
    {
        $movement->payment_status = FranceReportingStatus::Rejected->value;
        $movement->save();
    }

    private function claimTransport(TransactionEvent $submission): bool
    {
        return DB::transaction(function () use ($submission): bool {
            $locked = TransactionEvent::query()->lockForUpdate()->find($submission->id);

            if (! $locked || ! in_array((int) $locked->payment_status, FranceReportingStatus::openValues(), true)) {
                return false;
            }

            $request = $locked->payment_request ?? [];
            $claimedAt = data_get($request, 'transport_claimed_at');

            if (is_string($claimedAt) && CarbonImmutable::parse($claimedAt)->greaterThan(now()->subMinutes(5))) {
                return false;
            }

            $request['transport_claimed_at'] = now()->toIso8601String();
            $locked->payment_request = $request;
            $locked->save();

            return true;
        }, attempts: 3);
    }
}
