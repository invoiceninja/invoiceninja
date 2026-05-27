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
use App\Models\Payment;
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

class FranceEReportingCron implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function handle(): void
    {
        if (config("ninja.db.multi_db_enabled")) {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);
                $this->processDatabase($db);
            }

            return;
        }

        $this->processDatabase((string) config("database.default"));
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

    private function processDatabase(string $db): void
    {
        $parisNow = CarbonImmutable::now("Europe/Paris");

        Company::query()
            ->with("account")
            ->where("is_disabled", false)
            ->whereHas("account", fn ($query) => $query->where("is_flagged", false))
            ->cursor()
            ->each(function (Company $company) use ($db, $parisNow): void {
                if (! (bool) $company->getSetting("france_reporting_enabled")) {
                    return;
                }

                $this->processCompany($company, $company->db ?: $db, $parisNow);
            });
    }

    private function processCompany(Company $company, string $db, CarbonImmutable $parisNow): void
    {
        $this->recordCompletedPaymentActivity($company, $db, $parisNow);
        $this->dispatchPendingPaymentNotifications($company, $db);
        $this->dispatchDueReportSubmissions($company, $db, $parisNow);
    }

    private function recordCompletedPaymentActivity(Company $company, string $db, CarbonImmutable $parisNow): void
    {
        $lookback = $parisNow->subDays(7)->startOfDay()->timestamp;

        Payment::query()
            ->with([
                "client.country",
                "client.company",
                "company",
            ])
            ->where("company_id", $company->id)
            ->where("status_id", Payment::STATUS_COMPLETED)
            ->where("is_deleted", false)
            ->where(function ($query) use ($lookback): void {
                $query->where("updated_at", ">=", $lookback)
                    ->orWhereHas("paymentables", function ($paymentableQuery) use ($lookback): void {
                        $paymentableQuery
                            ->where("paymentable_type", "invoices")
                            ->where("updated_at", ">=", $lookback);
                    });
            })
            ->cursor()
            ->each(function (Payment $payment) use ($db): void {
                (new RecordFranceEReportingPayment($payment->id, $db))->handle();
            });
    }

    private function dispatchPendingPaymentNotifications(Company $company, string $db): void
    {
        TransactionEvent::query()
            ->where("company_id", $company->id)
            ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->whereIn("payment_status", [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_FAILED,
            ])
            ->orderBy("id")
            ->cursor()
            ->each(function (TransactionEvent $event) use ($db): void {
                if (data_get($event->payment_request, "skip_reason")) {
                    return;
                }

                SubmitFrancePaymentReceivedNotification::dispatch($event->id, $db);
            });
    }

    private function dispatchDueReportSubmissions(Company $company, string $db, CarbonImmutable $parisNow): void
    {
        foreach ($this->dueSubmissionWindows($company, $parisNow) as $submissionEventId => $periods) {
            foreach ($periods as $period) {
                if ($this->submissionAlreadySubmitted($company, $submissionEventId, $period)) {
                    continue;
                }

                SubmitFranceEReport::dispatch($company->id, $submissionEventId, $period->end->toDateString(), $db);
            }
        }
    }

    /**
     * @return array<int, array<string, ReportingPeriod>>
     */
    private function dueSubmissionWindows(Company $company, CarbonImmutable $parisNow): array
    {
        $profile = ReportingProfile::tryFrom((string) $company->getSetting("france_reporting_schedule"))
            ?? ReportingProfile::TenDay;
        $b2cPeriods = $this->duePeriods($profile, $parisNow);
        $vatExcludedPeriods = $this->duePeriods(ReportingProfile::BiMonthly, $parisNow);
        $correctivePeriods = [];

        foreach ([$b2cPeriods, $vatExcludedPeriods] as $periods) {
            foreach ($periods as $period) {
                $correctivePeriods[$period->end->toDateString()] = $period;
            }
        }

        return [
            TransactionEvent::FR_REPORT_SUBMISSION_B2C => $b2cPeriods,
            TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED => $vatExcludedPeriods,
            TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE => $correctivePeriods,
        ];
    }

    /**
     * @return array<string, ReportingPeriod>
     */
    private function duePeriods(ReportingProfile $profile, CarbonImmutable $parisNow): array
    {
        $periods = [];
        $today = $parisNow->toDateString();

        for ($daysBack = 0; $daysBack <= 75; $daysBack++) {
            $period = ReportingCalendar::currentPeriod($profile, $parisNow->subDays($daysBack));

            if ($period->dueDate->toDateString() === $today) {
                $periods[$period->end->toDateString()] = $period;
            }
        }

        return $periods;
    }

    private function submissionAlreadySubmitted(Company $company, int $submissionEventId, ReportingPeriod $period): bool
    {
        return TransactionEvent::query()
            ->where("company_id", $company->id)
            ->where("event_id", $submissionEventId)
            ->whereDate("period", $period->end->toDateString())
            ->whereIn("payment_status", [
                TransactionEvent::FR_REPORTING_STATUS_COMPILED,
                TransactionEvent::FR_REPORTING_STATUS_SUBMITTED,
            ])
            ->exists();
    }
}

