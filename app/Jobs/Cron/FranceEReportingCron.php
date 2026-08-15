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

namespace App\Jobs\Cron;

use App\Jobs\EDocument\SubmitFranceEReport;
use App\Jobs\EDocument\RecordFranceEReportingScopeInvalidation;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FrancePaymentNotificationProcessor;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingScopePlanner;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceSubmissionCallbackStore;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class FranceEReportingCron implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $timeout = 3500;

    private const UNMATCHED_CALLBACK_RETENTION_DAYS = 30;
    private const SOURCE_RECONCILIATION_OVERLAP_MINUTES = 1;

    public function __construct(
        private ?int $companyId = null,
        private ?string $db = null,
    ) {}

    public function handle(
        FranceReportingScopePlanner $scopePlanner,
        FranceReportMaterializer $materializer,
        FrancePaymentNotificationProcessor $notificationProcessor,
    ): void {
        $parisNow = CarbonImmutable::now('Europe/Paris');

        if ($this->companyId && $this->db) {
            MultiDB::setDb($this->db);
            $company = Company::query()
                ->whereKey($this->companyId)
                ->where('is_disabled', false)
                ->whereHas('account', fn($query) => $query->where('is_flagged', false))
                ->first();

            if ($company && (bool) $company->getSetting('france_reporting_enabled')) {
                $this->processCompany(
                    $company,
                    $this->db,
                    $parisNow,
                    $scopePlanner,
                    $materializer,
                    $notificationProcessor,
                );
            }

            return;
        }

        if (config('ninja.db.multi_db_enabled')) {
            foreach (MultiDB::$dbs as $db) {
                try {
                    MultiDB::setDB($db);
                    $this->dispatchCompanies($db);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            return;
        }

        $this->dispatchCompanies((string) config('database.default'));
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        $key = $this->companyId && $this->db
            ? 'france-e-reporting-company-' . sha1($this->db . '|' . $this->companyId)
            : 'france-e-reporting-cron-dispatch';

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(60)
                ->expireAfter(3600),
        ];
    }

    private function dispatchCompanies(string $db): void
    {
        Company::query()
            ->where('is_disabled', false)
            ->whereHas('account', fn($query) => $query->where('is_flagged', false))
            ->orderBy('id')
            ->cursor()
            ->filter(fn(Company $company): bool => (bool) $company->getSetting('france_reporting_enabled'))
            ->each(fn(Company $company) => self::dispatch($company->id, $company->db ?: $db));
    }

    private function processCompany(
        Company $company,
        string $db,
        CarbonImmutable $parisNow,
        FranceReportingScopePlanner $scopePlanner,
        FranceReportMaterializer $materializer,
        FrancePaymentNotificationProcessor $notificationProcessor,
    ): void {
        $scopePlanner->reset();

        try {
            $this->processScopeInvalidations($company);
            $this->reconcileSourceState($company, $db, $materializer);
            $this->replayStoredCallbacks($company);
            $this->dispatchPersistedSubmissions($company, $db, $notificationProcessor);
            $this->processPaymentNotifications($company, $db, $parisNow, $notificationProcessor);

            foreach ([FranceEReportVariant::TransactionInitial, FranceEReportVariant::PaymentInitial] as $family) {
                foreach ($scopePlanner->duePeriods($company, $family, $parisNow) as $period) {
                    try {
                        $submission = $materializer->materialize($company, $family, $period);
                    } catch (Throwable $exception) {
                        report($exception);

                        continue;
                    }

                    if ($submission) {
                        SubmitFranceEReport::dispatch($submission->id, $company->db ?: $db);
                    }
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            $scopePlanner->forget($company->id);
        }
    }

    private function reconcileSourceState(
        Company $company,
        string $db,
        FranceReportMaterializer $materializer,
    ): void
    {
        $reconciledThrough = CarbonImmutable::now('UTC');
        $watermark = $this->sourceReconciliationWatermark($company);
        $reportingContextHash = $materializer->reportingContextHash($company);
        $reportingProfile = (string) $company->getSetting('france_reporting_schedule');
        $contextChanged = $watermark
            && (! hash_equals(
                (string) data_get($watermark->payment_request, 'reporting_context_hash', ''),
                $reportingContextHash,
            ) || (string) data_get($watermark->payment_request, 'reporting_profile') !== $reportingProfile);
        $profileChanged = $watermark
            && (string) data_get($watermark->payment_request, 'reporting_profile') !== $reportingProfile;
        (new RecordFranceEReportingScopeInvalidation(
            $company->id,
            $company->db ?: $db,
            null,
            'scheduled-source-reconciliation',
            (bool) $profileChanged,
            false,
            true,
            sourceReconciliationSince: $this->sourceReconciliationSince($company),
            reconcileHistoricalScopeState: (bool) $contextChanged,
        ))->handle();

        $this->recordSourceReconciliationWatermark(
            $company,
            $reconciledThrough,
            $reportingContextHash,
            $reportingProfile,
        );
    }

    private function sourceReconciliationSince(Company $company): ?string
    {
        $watermark = $this->sourceReconciliationWatermark($company);
        $reconciledThrough = data_get($watermark?->payment_request, 'reconciled_through_at');

        return is_string($reconciledThrough) && $reconciledThrough !== ''
            ? CarbonImmutable::parse($reconciledThrough, 'UTC')
                ->subMinutes(self::SOURCE_RECONCILIATION_OVERLAP_MINUTES)
                ->toIso8601String()
            : null;
    }

    private function sourceReconciliationWatermark(Company $company): ?TransactionEvent
    {
        return TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', $this->clientIds($company))
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->where('payment_status', FranceReportingStatus::Accepted->value)
            ->where('payment_request->role', 'source_reconciliation_watermark')
            ->latest('id')
            ->first(['payment_request']);
    }

    private function recordSourceReconciliationWatermark(
        Company $company,
        CarbonImmutable $reconciledThrough,
        string $reportingContextHash,
        string $reportingProfile,
    ): void {
        $representativeClientId = Client::withTrashed()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->value('id');

        if (! $representativeClientId) {
            return;
        }

        DB::transaction(function () use (
            $company,
            $representativeClientId,
            $reconciledThrough,
            $reportingContextHash,
            $reportingProfile,
        ): void {
            TransactionEvent::query()
                ->where('company_id', $company->id)
                ->whereIn('client_id', $this->clientIds($company))
                ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
                ->where('payment_status', FranceReportingStatus::Accepted->value)
                ->where('payment_request->role', 'source_reconciliation_watermark')
                ->delete();
            TransactionEvent::create([
                'company_id' => $company->id,
                'client_id' => $representativeClientId,
                'invoice_id' => 0,
                'payment_id' => 0,
                'credit_id' => 0,
                'event_id' => FranceReportingEventType::ScopeInvalidation->value,
                'timestamp' => now()->timestamp,
                'period' => now('Europe/Paris')->toDateString(),
                'payment_status' => FranceReportingStatus::Accepted->value,
                'reporting_data' => null,
                'payment_request' => [
                    'schema_version' => 1,
                    'role' => 'source_reconciliation_watermark',
                    'reconciled_through_at' => $reconciledThrough->toIso8601String(),
                    'reporting_context_hash' => $reportingContextHash,
                    'reporting_profile' => $reportingProfile,
                ],
            ]);
        }, attempts: 3);
    }

    private function processScopeInvalidations(Company $company): void
    {
        TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', $this->clientIds($company))
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->whereNull('payment_status')
            ->orderBy('id')
            ->eachById(function (TransactionEvent $event) use ($company): void {
                try {
                    $request = $event->payment_request ?? [];
                    (new RecordFranceEReportingScopeInvalidation(
                        companyId: $company->id,
                        db: $company->db,
                        clientId: data_get($request, 'client_id'),
                        invalidationKey: (string) data_get($request, 'invalidation_key'),
                        supersedeUnacceptedTransactionScopes: (bool) data_get(
                            $request,
                            'supersede_unaccepted_transaction_scopes',
                        ),
                        initializeCurrentPeriods: (bool) data_get($request, 'initialize_current_periods'),
                        clientIds: array_map('intval', data_get($request, 'client_ids', [])),
                        invalidationEventId: $event->id,
                    ))->handle();
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }

    private function replayStoredCallbacks(Company $company): void
    {
        TransactionEvent::query()
            ->select(['id', 'company_id', 'payment_request'])
            ->where('company_id', $company->id)
            ->whereIn('client_id', $this->clientIds($company))
            ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
            ->where('payment_status', FranceReportingStatus::Pending->value)
            ->orderBy('id')
            ->eachById(function (TransactionEvent $callback) use ($company): void {
                $guid = trim((string) data_get($callback->payment_request, 'guid'));

                if ($guid !== '') {
                    app(FranceSubmissionCallbackStore::class)->replay($company, $guid);
                }
            });

        TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', $this->clientIds($company))
            ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
            ->where('payment_status', FranceReportingStatus::Pending->value)
            ->where('timestamp', '<', now()->subDays(self::UNMATCHED_CALLBACK_RETENTION_DAYS)->timestamp)
            ->delete();
    }

    private function dispatchPersistedSubmissions(
        Company $company,
        string $db,
        FrancePaymentNotificationProcessor $notificationProcessor,
    ): void
    {
        TransactionEvent::query()
            ->select(['id', 'event_id', 'payment_status', 'payment_request'])
            ->where('company_id', $company->id)
            ->whereIn('client_id', $this->clientIds($company))
            ->whereIn('event_id', [
                FranceReportingEventType::ReportSubmission->value,
                FranceReportingEventType::PaymentNotificationSubmission->value,
            ])
            ->whereIn('payment_status', [
                FranceReportingStatus::Pending->value,
                FranceReportingStatus::RetryableFailure->value,
            ])
            ->whereNull('payment_request->retry_exhausted_at')
            ->whereNull('payment_request->deferred_at')
            ->eachById(function (TransactionEvent $event) use ($db, $notificationProcessor): void {
                if ((int) $event->event_id === FranceReportingEventType::ReportSubmission->value) {
                    SubmitFranceEReport::dispatch($event->id, $db);

                    return;
                }

                try {
                    $notificationProcessor->process($event->id, $db);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }

    private function processPaymentNotifications(
        Company $company,
        string $db,
        CarbonImmutable $parisNow,
        FrancePaymentNotificationProcessor $notificationProcessor,
    ): void {
        $processedGroups = [];

        TransactionEvent::query()
            ->select([
                'id',
                'company_id',
                'client_id',
                'invoice_id',
                'payment_id',
                'credit_id',
                'event_id',
                'period',
                'payment_status',
                'payment_request',
            ])
            ->where('company_id', $company->id)
            ->whereIn('client_id', $this->clientIds($company))
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->whereNull('payment_status')
            ->where('payment_request->reporting_path', 'payment_received_notification')
            ->eachById(function (TransactionEvent $event) use (
                $db,
                $parisNow,
                $notificationProcessor,
                &$processedGroups,
            ): void {
                $effectiveAt = (string) data_get($event->payment_request, 'effective_at');

                if ($effectiveAt !== ''
                    && CarbonImmutable::parse($effectiveAt, 'Europe/Paris')->startOfDay()->greaterThan($parisNow->startOfDay())) {
                    return;
                }

                $groupKey = implode('|', [
                    (string) $event->invoice_id,
                    trim((string) data_get($event->payment_request, 'original_document_guid')),
                ]);

                if (isset($processedGroups[$groupKey])) {
                    return;
                }

                $processedGroups[$groupKey] = true;

                try {
                    $notificationProcessor->process($event->id, $db);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }

    private function clientIds(Company $company): Builder
    {
        return Client::withTrashed()
            ->select('id')
            ->where('company_id', $company->id);
    }
}
