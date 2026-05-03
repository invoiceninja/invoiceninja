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

use Carbon\Carbon;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Models\Timezone;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Jobs\Entity\EmailEntity;
use App\Models\TransactionEvent;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Listeners\Invoice\InvoiceTransactionEventEntry;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;

class InvoiceTaxSummary implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function __construct() {}

    public function handle()
    {
        nlog("InvoiceTaxSummary:: Starting job @ " . now()->toDateTimeString());
        $start = now();

        $transitioningTimezones = $this->getTransitioningTimezones(now('UTC'));

        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);
            // Only process companies in timezones that just transitioned
            $companies = $this->getCompaniesInTimezones($transitioningTimezones);

            foreach ($companies as $company) {
                $this->processCompanyTaxSummary($company);
            }
        }

        nlog("InvoiceTaxSummary:: Job completed in " . (int) now()->diffInSeconds($start) . " seconds");
    }

    private function getTransitioningTimezones(?Carbon $utcNow = null): array
    {
        $utcNow = ($utcNow ?? now('UTC'))->copy()->setTimezone('UTC');
        $transitioningTimezones = [];

        // Get all timezones from the database
        $timezones = app('timezones');

        /** @var \App\Models\Timezone $timezone */
        foreach ($timezones as $timezone) {
            if ($this->timezoneCrossedIntoNewMonth($timezone->name, $utcNow)) {
                $transitioningTimezones[] = $timezone->id;
            }
        }

        return $transitioningTimezones;
    }

    private function timezoneCrossedIntoNewMonth(string $timezoneName, Carbon $utcNow): bool
    {
        try {
            $localNow = $utcNow->copy()->setTimezone($timezoneName);
            $localPreviousHour = $utcNow->copy()->subHour()->setTimezone($timezoneName);
        } catch (\Exception $e) {
            return false;
        }

        return $localNow->day === 1
            && $localNow->format('Y-m') !== $localPreviousHour->format('Y-m');
    }

    private function getCompaniesInTimezones($timezoneIds)
    {
        if (empty($timezoneIds)) {
            return collect(); // No companies to process
        }

        // Get companies that have timezone_id in their JSON settings matching the transitioning timezones
        $companies = Company::whereRaw("JSON_EXTRACT(settings, '$.timezone_id') IN (" . implode(',', $timezoneIds) . ")")->get();

        nlog("InvoiceTaxSummary:: Found " . $companies->count() . " companies in timezones: " . implode(',', $timezoneIds));

        return $companies;
    }

    private function processCompanyTaxSummary($company)
    {
        $timezone = $company->timezone()->name ?? 'UTC';
        $yesterdayLocal = now()->setTimezone($timezone)->subDay();

        // Only process when yesterday was the last day of the month
        // (i.e., company's local timezone just crossed into a new month)
        if ($yesterdayLocal->day !== $yesterdayLocal->daysInMonth) {
            return;
        }

        $startDate = $yesterdayLocal->copy()->startOfMonth()->format('Y-m-d');
        $endDate = $yesterdayLocal->copy()->endOfMonth()->format('Y-m-d');

        $lock = Cache::lock($this->taxSummaryLockKey($company, $endDate), 21600);

        if (! $lock->get()) {
            nlog("InvoiceTaxSummary:: Skipping company {$company->id}; summary already running for {$endDate}");
            return;
        }

        try {
            $this->generateTaxSummary($company, $startDate, $endDate);
        } finally {
            $lock->release();
        }
    }

    private function taxSummaryLockKey(Company $company, string $period): string
    {
        $companyKey = $company->company_key ?: $company->id;
        $database = config('database.default');

        return "invoice-tax-summary:{$database}:{$companyKey}:{$period}";
    }

    private function generateTaxSummary($company, $startDate, $endDate)
    {
        // Convert company timezone dates to UTC for database query
        // $startDate and $endDate are in Y-m-d format (e.g., "2024-01-01")
        $timezone = $company->timezone()->name ?? 'UTC';
        $startDateUtc = Carbon::createFromFormat('Y-m-d', $startDate, $timezone)
            ->startOfDay()
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
        $endDateUtc = Carbon::createFromFormat('Y-m-d', $endDate, $timezone)
            ->endOfDay()
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');

        Invoice::withTrashed()
                ->with('payments')
                ->where('company_id', $company->id)
                ->whereIn('status_id', [2,3,4,5])
                // ->where('is_deleted', 0) I still need to assess deleted invoices, and ensure if there is an entry present, we reverse it!!!
                ->whereHas('client', function ($query) {
                    $query->where('is_deleted', false);
                })
                ->whereHas('company', function ($query) {
                    $query->where('is_disabled', 0)
                    ->whereHas('account', function ($q) {
                        $q->where('is_flagged', false);
                    });
                })
                ->whereBetween('updated_at', [$startDateUtc, $endDateUtc])
                ->cursor()
                ->each(function (Invoice $invoice) use ($endDate) {
                    (new InvoiceTransactionEventEntry())->run($invoice, $endDate);
                });

        Invoice::withTrashed()
                ->with('payments')
                ->where('company_id', $company->id)
                ->whereIn('status_id', [3,4]) // Paid statuses
                ->where('is_deleted', 0)
                ->whereColumn('amount', '!=', 'balance')
                ->whereHas('client', function ($query) {
                    $query->where('is_deleted', false);
                })
                ->whereHas('company', function ($query) {
                    $query->where('is_disabled', 0)
                    ->whereHas('account', function ($q) {
                        $q->where('is_flagged', false);
                    });
                })
                ->whereHas('payments', function ($query) use ($startDateUtc, $endDateUtc) {
                    $query->whereHas('paymentables', function ($subQuery) use ($startDateUtc, $endDateUtc) {
                        $subQuery->where('paymentable_type', 'invoices')
                                ->whereBetween('created_at', [$startDateUtc, $endDateUtc]);
                    });
                })
                ->whereDoesntHave('transaction_events', function ($q) use ($endDate) {
                    $q->where('event_id', TransactionEvent::PAYMENT_CASH)
                        ->where('period', $endDate);
                })
                ->cursor()
                ->each(function (Invoice $invoice) use ($startDate, $endDate) {
                    (new InvoiceTransactionEventEntryCash())->run($invoice, $startDate, $endDate);
                });

    }

}
