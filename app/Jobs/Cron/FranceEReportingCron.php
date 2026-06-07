<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://www.invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Cron;

use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Jobs\EDocument\SubmitFranceEReport;
use App\Jobs\EDocument\SubmitFrancePaymentReceivedNotification;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingPeriod;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FranceEReportingCron implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;
    private const REPORT_SUBMISSION_LEAD_DAYS = 3;

    private const REPORT_LATE_RECOVERY_DAYS = 7;


    /**
     * Execute the France e-reporting daily reconciliation for each configured database.
     *
     * The Paris timestamp is captured once so notification and report due-window decisions use one consistent reporting day.
     */
    public function handle(): void
    {
        $parisNow = CarbonImmutable::now("Europe/Paris");

        if (config("ninja.db.multi_db_enabled")) {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);
                $this->processDatabase($db, $parisNow);
            }

            return;
        }

        $this->processDatabase((string) config("database.default"), $parisNow);
    }

    /**
     * Prevent overlapping cron executions for the same scheduler run.
     *
     * This keeps duplicate workers from dispatching the same pending France submissions at the same time.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("france-e-reporting-cron"))
                ->releaseAfter(60)
                ->expireAfter(3600),
        ];
    }

    /**
     * Process one physical database connection for France e-reporting work.
     *
     * Daily payment notifications are always considered. Period reports only query source rows when the France reporting calendar says a period is eligible.
     */
    private function processDatabase(string $db, CarbonImmutable $parisNow): void
    {
        /** First send out any pending payment notifications (FR => FR B2B Payment Received Notification) */
        $this->dispatchPendingPaymentNotifications($db);

        /** Then submit any due report submissions (Submission) */
        $duePeriods = $this->dueReportPeriods($parisNow);

        if ($duePeriods->isEmpty()) {
            return;
        }

        $this->dispatchDueReportSubmissions($db, $parisNow, $duePeriods);
    }

    /**
     * Dispatch Storecove payment-received notification jobs for pending FR B2B notification events.
     *
     * The source of truth is transaction_events; companies are loaded in batches from the event company ids before event rows are dispatched.
     */
    private function dispatchPendingPaymentNotifications(string $db): void
    {
        $this->pendingPaymentNotificationCompanyIds()
            ->chunk(500)
            ->each(function (Collection $companyIds) use ($db): void {
                $companies = $this->reportableCompanies($companyIds->all());

                if ($companies->isEmpty()) {
                    return;
                }

                TransactionEvent::query()
                    ->whereIn("company_id", $companies->keys()->all())
                    ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
                    ->whereIn("payment_status", [
                        TransactionEvent::FR_REPORTING_STATUS_PENDING,
                        TransactionEvent::FR_REPORTING_STATUS_FAILED,
                    ])
                    ->orderBy("company_id")
                    ->orderBy("id")
                    ->cursor()
                    ->each(function (TransactionEvent $event) use ($companies, $db): void {
                        if (data_get($event->payment_request, "skip_reason")) {
                            return;
                        }

                        $company = $companies->get((int) $event->company_id);

                        if (! $company) {
                            return;
                        }

                        SubmitFrancePaymentReceivedNotification::dispatch($event->id, $company->db ?: $db);
                    });
            });
    }

    /**
     * Dispatch report submission jobs for source event periods that are eligible in Europe/Paris.
     *
     * This method is only called after the standardized France calendar has produced at least one eligible period for the day.
     *
     * @param Collection<string, ReportingPeriod> $duePeriods
     */
    private function dispatchDueReportSubmissions(string $db, CarbonImmutable $parisNow, Collection $duePeriods): void
    {
        $this->dispatchDueInitialReportSubmissions($db, $parisNow, $duePeriods);
        $this->dispatchDueCorrectiveReportSubmissions($db, $parisNow, $duePeriods);
    }

    /**
     * Find initial B2C and VAT-excluded report groups for the due period set.
     *
     * Source rows are reduced at the database level, then deduplicated by company, submission type, and period so mixed transaction/payment buckets submit once.
     *
     * @param Collection<string, ReportingPeriod> $duePeriods
     */
    private function dispatchDueInitialReportSubmissions(string $db, CarbonImmutable $parisNow, Collection $duePeriods): void
    {
        $dispatched = [];

        TransactionEvent::query()
            ->select(["company_id", "event_id", "period"])
            ->whereIn("event_id", [
                TransactionEvent::FR_B2C_TRANSACTION,
                TransactionEvent::FR_B2C_PAYMENT,
                TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            ])
            ->whereIn("payment_status", [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_FAILED,
            ])
            ->whereIn("period", $duePeriods->keys()->all())
            ->where(function ($query): void {
                $query->whereNull("payment_request->fr_report_kind")
                    ->orWhere("payment_request->fr_report_kind", RecordFranceEReportingPayment::REPORT_KIND_INITIAL);
            })
            ->groupBy("company_id", "event_id", "period")
            ->orderBy("company_id")
            ->orderBy("event_id")
            ->orderBy("period")
            ->chunk(500, function (Collection $events) use ($db, $parisNow, &$dispatched): void {
                $companies = $this->reportableCompanies($events->pluck("company_id")->all());

                $events->each(function (TransactionEvent $event) use ($companies, $db, $parisNow, &$dispatched): void {
                    $company = $companies->get((int) $event->company_id);

                    if (! $company) {
                        return;
                    }

                    $periodEnd = $this->periodEnd($event);
                    $submissionEventId = $this->submissionEventForSourceEvent((int) $event->event_id);

                    if (is_null($periodEnd)) {
                        return;
                    }

                    $key = $event->company_id."|".$submissionEventId."|".$periodEnd;

                    if (isset($dispatched[$key])) {
                        return;
                    }

                    $dispatched[$key] = true;

                    $this->dispatchDueSourceGroup(
                        event: $event,
                        company: $company,
                        submissionEventId: $submissionEventId,
                        db: $db,
                        parisNow: $parisNow,
                    );
                });
            });
    }

    /**
     * Find corrective payment report groups for the due period set.
     *
     * Corrective submissions can contain multiple payment source event types, so the cron deduplicates by company and period before dispatching.
     *
     * @param Collection<string, ReportingPeriod> $duePeriods
     */
    private function dispatchDueCorrectiveReportSubmissions(string $db, CarbonImmutable $parisNow, Collection $duePeriods): void
    {
        $dispatched = [];

        TransactionEvent::query()
            ->select(["company_id", "event_id", "period"])
            ->whereIn("event_id", [
                TransactionEvent::FR_B2C_PAYMENT,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            ])
            ->whereIn("payment_status", [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_FAILED,
            ])
            ->whereIn("period", $duePeriods->keys()->all())
            ->where("payment_request->fr_report_kind", RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE)
            ->groupBy("company_id", "event_id", "period")
            ->orderBy("company_id")
            ->orderBy("event_id")
            ->orderBy("period")
            ->chunk(500, function (Collection $events) use ($db, $parisNow, &$dispatched): void {
                $companies = $this->reportableCompanies($events->pluck("company_id")->all());

                $events->each(function (TransactionEvent $event) use ($companies, $db, $parisNow, &$dispatched): void {
                    $company = $companies->get((int) $event->company_id);
                    $periodEnd = $this->periodEnd($event);

                    if (! $company || is_null($periodEnd)) {
                        return;
                    }

                    $key = $event->company_id."|".$periodEnd;

                    if (isset($dispatched[$key])) {
                        return;
                    }

                    $dispatched[$key] = true;

                    $this->dispatchDueSourceGroup(
                        event: $event,
                        company: $company,
                        submissionEventId: TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE,
                        db: $db,
                        parisNow: $parisNow,
                    );
                });
            });
    }

    /**
     * Validate a grouped source-event bucket and dispatch the matching report submission job.
     *
     * The bucket is ignored when the company cadence does not match the source period or a submitted report already exists.
     */
    private function dispatchDueSourceGroup(TransactionEvent $event, Company $company, int $submissionEventId, string $db, CarbonImmutable $parisNow): void
    {
        $periodEnd = $this->periodEnd($event);

        if (is_null($periodEnd) || ! $this->sourcePeriodIsDue($company, (int) $event->event_id, $periodEnd, $parisNow)) {
            return;
        }

        if ($this->submissionAlreadyAccepted((int) $company->id, $submissionEventId, $periodEnd)) {
            return;
        }

        SubmitFranceEReport::dispatch($company->id, $submissionEventId, $periodEnd, $company->db ?: $db);
    }

    /**
     * Calculate the standardized France reporting periods eligible for submission today in Europe/Paris.
     *
     * A period is eligible from the configured lead window before its due date through the bounded late recovery window after its due date.
     *
     * @return Collection<string, ReportingPeriod>
     */
    private function dueReportPeriods(CarbonImmutable $parisNow): Collection
    {
        $today = $parisNow->startOfDay();

        return collect(ReportingProfile::cases())
            ->flatMap(fn (ReportingProfile $profile): Collection => $this->candidatePeriods($profile, $parisNow))
            ->filter(fn (ReportingPeriod $period): bool => $this->periodIsEligibleForSubmission($period, $today))
            ->keyBy(fn (ReportingPeriod $period): string => $period->end->toDateString());
    }

    /**
     * Build candidate periods for a standardized France profile around the current day.
     *
     * The 75-day lookback covers bi-monthly reporting windows while remaining fixed and independent of transaction volume.
     *
     * @return Collection<string, ReportingPeriod>
     */
    private function candidatePeriods(ReportingProfile $profile, CarbonImmutable $parisNow): Collection
    {
        return collect(range(0, 75))
            ->map(fn (int $daysBack): ReportingPeriod => ReportingCalendar::currentPeriod($profile, $parisNow->subDays($daysBack)))
            ->keyBy(fn (ReportingPeriod $period): string => $period->end->toDateString());
    }

    /**
     * Return the company ids represented by pending payment-received notification events.
     *
     * This keeps notification dispatch company-scoped without querying every France-enabled company.
     *
     * @return Collection<int, int|string>
     */
    private function pendingPaymentNotificationCompanyIds(): Collection
    {
        return TransactionEvent::query()
            ->select("company_id")
            ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->whereIn("payment_status", [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_FAILED,
            ])
            ->groupBy("company_id")
            ->orderBy("company_id")
            ->pluck("company_id");
    }

    /**
     * Load active, unflagged, France-reporting-enabled companies for a batch of transaction event company ids.
     *
     * This is the only company hydration point in the cron and it is batch-oriented by design.
     *
     * @param array<int, int|string> $companyIds
     * @return Collection<int, Company>
     */
    private function reportableCompanies(array $companyIds): Collection
    {
        $companyIds = collect($companyIds)
            ->map(fn ($companyId): int => (int) $companyId)
            ->filter(fn (int $companyId): bool => $companyId > 0)
            ->unique()
            ->values();

        if ($companyIds->isEmpty()) {
            return collect();
        }

        return Company::query()
            ->with("account")
            ->whereIn("id", $companyIds->all())
            ->where("is_disabled", false)
            ->whereHas("account", fn ($query) => $query->where("is_flagged", false))
            ->get()
            ->filter(fn (Company $company): bool => (bool) $company->getSetting("france_reporting_enabled"))
            ->keyBy(fn (Company $company): int => (int) $company->id);
    }

    /**
     * Determine whether the grouped source period is eligible for submission on the current Paris reporting day.
     *
     * This verifies the source period against the company cadence because the standardized due-period set can contain overlapping profile dates.
     */
    private function sourcePeriodIsDue(Company $company, int $sourceEventId, string $periodEnd, CarbonImmutable $parisNow): bool
    {
        $period = ReportingCalendar::currentPeriod(
            $this->profileForSourceEvent($company, $sourceEventId),
            CarbonImmutable::parse($periodEnd, "Europe/Paris"),
        );

        return $period->end->toDateString() === $periodEnd
            && $this->periodIsEligibleForSubmission($period, $parisNow->startOfDay());
    }

    /**
     * Check whether today falls inside the pre-due submission window or bounded late recovery window.
     */
    private function periodIsEligibleForSubmission(ReportingPeriod $period, CarbonImmutable $today): bool
    {
        $windowStart = $period->dueDate
            ->subDays(self::REPORT_SUBMISSION_LEAD_DAYS)
            ->startOfDay();
        $windowEnd = $period->dueDate
            ->addDays(self::REPORT_LATE_RECOVERY_DAYS)
            ->endOfDay();

        return $today->greaterThanOrEqualTo($windowStart)
            && $today->lessThanOrEqualTo($windowEnd);
    }

    /**
     * Resolve the reporting cadence that controls a source event type.
     *
     * VAT-excluded transactions/payments are always bi-monthly; B2C transactions/payments follow the company France reporting schedule with ten-day as the fallback.
     */
    private function profileForSourceEvent(Company $company, int $sourceEventId): ReportingProfile
    {
        if (in_array($sourceEventId, [
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
        ], true)) {
            return ReportingProfile::BiMonthly;
        }

        return ReportingProfile::tryFrom((string) $company->getSetting("france_reporting_schedule"))
            ?? ReportingProfile::TenDay;
    }

    /**
     * Map a source event id to the Storecove report submission event id.
     *
     * This keeps the grouped transaction_events query source-oriented while dispatching the correct submission job type.
     */
    private function submissionEventForSourceEvent(int $sourceEventId): int
    {
        return in_array($sourceEventId, [
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
        ], true)
            ? TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED
            : TransactionEvent::FR_REPORT_SUBMISSION_B2C;
    }

    /**
     * Normalize the transaction event period to the date string expected by the report compiler.
     */
    private function periodEnd(TransactionEvent $event): ?string
    {
        return $event->period?->toDateString();
    }

    /**
     * Check whether this company, submission type, and period has already been accepted by Storecove.
     *
     * Failed submissions are intentionally not treated as complete, allowing a future cron run to retry the same grouped source rows.
     */
    private function submissionAlreadyAccepted(int $companyId, int $submissionEventId, string $periodEnd): bool
    {
        return TransactionEvent::query()
            ->where("company_id", $companyId)
            ->where("event_id", $submissionEventId)
            ->whereDate("period", $periodEnd)
            ->where("payment_status", TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
            ->exists();
    }
}

