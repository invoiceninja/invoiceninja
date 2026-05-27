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
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class FranceEReportingCron implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    /** @var array<int, Company|null> */
    private array $companies = [];

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

    private function processDatabase(string $db, CarbonImmutable $parisNow): void
    {
        $this->companies = [];

        $this->dispatchPendingPaymentNotifications($db);
        $this->dispatchDueReportSubmissions($db, $parisNow);
    }

    private function dispatchPendingPaymentNotifications(string $db): void
    {
        TransactionEvent::query()
            ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->whereIn("payment_status", [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_FAILED,
            ])
            ->orderBy("company_id")
            ->orderBy("id")
            ->cursor()
            ->each(function (TransactionEvent $event) use ($db): void {
                if (data_get($event->payment_request, "skip_reason")) {
                    return;
                }

                $company = $this->reportingCompany((int) $event->company_id);

                if (! $company) {
                    return;
                }

                SubmitFrancePaymentReceivedNotification::dispatch($event->id, $company->db ?: $db);
            });
    }

    private function dispatchDueReportSubmissions(string $db, CarbonImmutable $parisNow): void
    {
        $this->dispatchDueInitialReportSubmissions($db, $parisNow);
        $this->dispatchDueCorrectiveReportSubmissions($db, $parisNow);
    }

    private function dispatchDueInitialReportSubmissions(string $db, CarbonImmutable $parisNow): void
    {
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
            ->whereNotNull("period")
            ->where(function ($query): void {
                $query->whereNull("payment_request->fr_report_kind")
                    ->orWhere("payment_request->fr_report_kind", RecordFranceEReportingPayment::REPORT_KIND_INITIAL);
            })
            ->groupBy("company_id", "event_id", "period")
            ->orderBy("company_id")
            ->orderBy("period")
            ->cursor()
            ->each(function (TransactionEvent $event) use ($db, $parisNow): void {
                $this->dispatchDueSourceGroup($event, $this->submissionEventForSourceEvent((int) $event->event_id), $db, $parisNow);
            });
    }

    private function dispatchDueCorrectiveReportSubmissions(string $db, CarbonImmutable $parisNow): void
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
            ->whereNotNull("period")
            ->where("payment_request->fr_report_kind", RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE)
            ->groupBy("company_id", "event_id", "period")
            ->orderBy("company_id")
            ->orderBy("period")
            ->cursor()
            ->each(function (TransactionEvent $event) use ($db, $parisNow, &$dispatched): void {
                $periodEnd = $this->periodEnd($event);

                if (is_null($periodEnd)) {
                    return;
                }

                $key = $event->company_id."|".$periodEnd;

                if (isset($dispatched[$key])) {
                    return;
                }

                $dispatched[$key] = true;
                $this->dispatchDueSourceGroup($event, TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE, $db, $parisNow);
            });
    }

    private function dispatchDueSourceGroup(TransactionEvent $event, int $submissionEventId, string $db, CarbonImmutable $parisNow): void
    {
        $periodEnd = $this->periodEnd($event);

        if (is_null($periodEnd)) {
            return;
        }

        $company = $this->reportingCompany((int) $event->company_id);

        if (! $company || ! $this->sourcePeriodIsDue($company, (int) $event->event_id, $periodEnd, $parisNow)) {
            return;
        }

        if ($this->submissionAlreadySubmitted((int) $company->id, $submissionEventId, $periodEnd)) {
            return;
        }

        SubmitFranceEReport::dispatch($company->id, $submissionEventId, $periodEnd, $company->db ?: $db);
    }

    private function reportingCompany(int $companyId): ?Company
    {
        if (! array_key_exists($companyId, $this->companies)) {
            $company = Company::query()
                ->with("account")
                ->where("id", $companyId)
                ->where("is_disabled", false)
                ->whereHas("account", fn ($query) => $query->where("is_flagged", false))
                ->first();

            if ($company && ! (bool) $company->getSetting("france_reporting_enabled")) {
                $company = null;
            }

            $this->companies[$companyId] = $company;
        }

        return $this->companies[$companyId];
    }

    private function sourcePeriodIsDue(Company $company, int $sourceEventId, string $periodEnd, CarbonImmutable $parisNow): bool
    {
        $period = ReportingCalendar::currentPeriod(
            $this->profileForSourceEvent($company, $sourceEventId),
            CarbonImmutable::parse($periodEnd, "Europe/Paris"),
        );

        return $period->end->toDateString() === $periodEnd
            && $period->dueDate->toDateString() === $parisNow->toDateString();
    }

    private function profileForSourceEvent(Company $company, int $sourceEventId): ReportingProfile
    {
        if ($sourceEventId === TransactionEvent::FR_VAT_EXCLUDED_PAYMENT) {
            return ReportingProfile::BiMonthly;
        }

        return ReportingProfile::tryFrom((string) $company->getSetting("france_reporting_schedule"))
            ?? ReportingProfile::TenDay;
    }

    private function submissionEventForSourceEvent(int $sourceEventId): int
    {
        return $sourceEventId === TransactionEvent::FR_VAT_EXCLUDED_PAYMENT
            ? TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED
            : TransactionEvent::FR_REPORT_SUBMISSION_B2C;
    }

    private function periodEnd(TransactionEvent $event): ?string
    {
        return $event->period?->toDateString();
    }

    private function submissionAlreadySubmitted(int $companyId, int $submissionEventId, string $periodEnd): bool
    {
        return TransactionEvent::query()
            ->where("company_id", $companyId)
            ->where("event_id", $submissionEventId)
            ->whereDate("period", $periodEnd)
            ->whereIn("payment_status", [
                TransactionEvent::FR_REPORTING_STATUS_COMPILED,
                TransactionEvent::FR_REPORTING_STATUS_SUBMITTED,
            ])
            ->exists();
    }
}

