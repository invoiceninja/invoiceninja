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
use App\Models\Account;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FranceEReportStorecoveProjection;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceSubmissionCallbackStore;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Transport one already-materialized report. This job never recompiles payloads.
 */
class SubmitFranceEReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    private const MAX_TRANSPORT_ATTEMPTS = 6;

    public function __construct(
        private int $submissionEventId,
        private string $db,
        private bool $force = false,
    ) {}

    public function handle(Storecove $storecove): void
    {
        MultiDB::setDb($this->db);

        /** @var TransactionEvent|null $submission */
        $submission = TransactionEvent::query()->find($this->submissionEventId);

        if (! $submission
            || (int) $submission->event_id !== FranceReportingEventType::ReportSubmission->value
            || ! in_array((int) $submission->payment_status, [
                FranceReportingStatus::Pending->value,
                FranceReportingStatus::RetryableFailure->value,
            ], true)) {
            return;
        }

        if (! $this->force
            && count((array) data_get($submission->payment_request, 'attempts', [])) >= self::MAX_TRANSPORT_ATTEMPTS) {
            $this->recordRetryExhaustion($submission, 'France report transport retry limit reached.');

            return;
        }

        /** @var Company|null $company */
        $company = Company::query()->find($submission->company_id);
        $variant = FranceEReportVariant::tryFrom((string) data_get($submission->payment_request, 'variant'));

        if (! $company
            || ! (bool) $company->getSetting('france_reporting_enabled')
            || ! $variant) {
            return;
        }

        $company->loadMissing('account');
        $account = $company->getRelation('account');

        if ($company->is_disabled
            || ! $account instanceof Account
            || $account->is_flagged) {
            return;
        }

        $payload = data_get($submission->payment_request, 'payload');
        $storedHash = (string) data_get($submission->payment_request, 'payload_hash');

        if (! is_array($payload)
            || $storedHash === ''
            || ! hash_equals(
                $storedHash,
                hash('sha256', json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)),
            )) {
            $this->recordTerminalFailure($submission, 'Stored France report payload is missing or has changed.');

            return;
        }

        try {
            $payload = FranceEReportStorecoveProjection::from($payload);

            if (! $this->claimTransport($submission)) {
                return;
            }

            $response = $storecove->proxy
                ->setCompany($company)
                ->submitDocument([
                    ...$payload,
                    'legal_entity_id' => $payload['legalEntityId'],
                    'tenant_id' => $company->company_key,
                    'account_key' => $account->key,
                    'e_invoicing_token' => $account->e_invoicing_token,
                ]);
            $guid = $response['guid'] ?? null;

            if (! is_string($guid) || trim($guid) === '') {
                throw new RuntimeException('Storecove France report response did not contain a GUID.');
            }

            $this->recordAttempt($submission, FranceReportingStatus::Sent, $response, null, $guid);
            app(FranceSubmissionCallbackStore::class)->replay($company, $guid);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordAttempt(
                $submission,
                FranceReportingStatus::RetryableFailure,
                [],
                ['message' => $exception->getMessage(), 'class' => $exception::class],
            );

            throw $exception;
        }
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->submissionEventId . $this->db . '.fr-e-report-transport'))
                ->releaseAfter(60)
                ->expireAfter(300),
        ];
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

            if (! $locked || (int) $locked->event_id !== FranceReportingEventType::ReportSubmission->value) {
                return;
            }

            if (in_array((int) $locked->payment_status, [
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
                $locked->payment_status = FranceReportingStatus::RetryableFailure->value;
                $locked->payment_request = $request;
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
            $locked->payment_status = $status->value;
            $locked->payment_request = $request;
            $locked->save();

            if ($status === FranceReportingStatus::Rejected) {
                $this->rejectCandidateSnapshots($locked, $request);

                if ($localDisposition === 'invalid_persisted_payload') {
                    app(FranceReportMaterializer::class)->reopenSubmissionFacts($locked);
                }
            }
        }, attempts: 3);
    }

    /** @param array<string, mixed> $request */
    private function rejectCandidateSnapshots(TransactionEvent $submission, array $request): void
    {
        $snapshotIds = array_values(array_filter(
            $request['snapshot_event_ids'] ?? [],
            static fn(mixed $id): bool => is_int($id) || ctype_digit((string) $id),
        ));

        TransactionEvent::query()
            ->where('company_id', $submission->company_id)
            ->whereIn('id', $snapshotIds)
            ->whereIn('event_id', [
                FranceReportingEventType::TransactionSnapshot->value,
                FranceReportingEventType::PaymentSnapshot->value,
            ])
            ->where('payment_request->submission_event_id', $submission->id)
            ->update(['payment_status' => FranceReportingStatus::Rejected->value]);
    }

    private function recordTerminalFailure(TransactionEvent $submission, string $message): void
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
                || (int) $locked->event_id !== FranceReportingEventType::ReportSubmission->value
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
            $locked->payment_request = $request;
            $locked->payment_status = FranceReportingStatus::RetryableFailure->value;
            $locked->save();
        }, attempts: 3);
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
