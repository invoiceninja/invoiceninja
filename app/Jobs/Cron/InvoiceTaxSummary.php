<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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

    public function __construct()
    {

    }

    public function handle()
    {
        nlog("InvoiceTaxSummary:: Starting job @ " . now()->toDateTimeString());
        $start = now();

        $currentUtcHour = now()->hour;
        $transitioningTimezones = $this->getTransitioningTimezones($currentUtcHour);
     
        foreach(MultiDB::$dbs as $db) {
            MultiDB::setDB($db);
            // Only process companies in timezones that just transitioned
            $companies = $this->getCompaniesInTimezones($transitioningTimezones);
            
            foreach ($companies as $company) {
                $this->processCompanyTaxSummary($company);
            }
        }

        nlog("InvoiceTaxSummary:: Job completed in " . now()->diffInSeconds($start) . " seconds");
    }

    private function getTransitioningTimezones($utcHour)
    {
        $transitioningTimezones = [];
        
        // Get all timezones from the database
        $timezones = app('timezones');
        
        /** @var \App\Models\Timezone $timezone */
        foreach ($timezones as $timezone) {
            // Calculate the current UTC offset for this timezone (accounting for DST)
            $currentOffset = $this->getCurrentUtcOffset($timezone->name);
            
            // Calculate when this timezone transitions to the next day
            $transitionHour = $this->getTimezoneTransitionHour($currentOffset);
            
            // If this timezone transitions at the current UTC hour, include it
            if ($transitionHour === $utcHour) {
                $transitioningTimezones[] = $timezone->id;
            }
        }

        return $transitioningTimezones;
    }

    private function getCurrentUtcOffset($timezoneName)
    {
        try {
            $dateTime = new \DateTime('now', new \DateTimeZone($timezoneName));
            return $dateTime->getOffset();
        } catch (\Exception $e) {
            // Fallback to UTC if timezone is invalid
            return 0;
        }
    }

    private function getTimezoneTransitionHour($utcOffset)
    {
        // Calculate which UTC hour this timezone transitions to the next day
        // A timezone with UTC offset +X transitions at UTC hour (24 - X)
        // For example: UTC+14 transitions at UTC 10:00 (24 - 14 = 10)
        // UTC-12 transitions at UTC 12:00 (24 - (-12) = 36, but we use modulo 24)
        
        $transitionHour = (24 - ($utcOffset / 3600)) % 24;
        
        // Handle negative offsets properly
        if ($transitionHour < 0) {
            $transitionHour += 24;
        }
        
        return (int) $transitionHour;
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
        // Your existing tax summary logic here
        // This will only run for companies in timezones that just transitioned
        
        $startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $endDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
        
        // Process tax summary for the company
        $this->generateTaxSummary($company, $startDate, $endDate);
    }

    private function generateTaxSummary($company, $startDate, $endDate)
    {
        $todayStart = now()->subHours(15)->timestamp;
        $todayEnd = now()->endOfDay()->timestamp;
        
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
                ->with('payments',)
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
                // ->whereBetween('date', [$startDate, $endDate])
                // ->whereDoesntHave('transaction_events', function ($query) use ($todayStart, $todayEnd) {
                //         $query->where('timestamp', '>=', $todayStart)
                //             ->where('timestamp', '<=', $todayEnd) 
                //             ->where('event_id', TransactionEvent::INVOICE_UPDATED);
                // })
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
                        $subQuery->where('paymentable_type', Invoice::class)
                                ->whereBetween('created_at', [$startDateUtc, $endDateUtc]);
                    });
                })
                ->whereDoesntHave('transaction_events', function ($q) use ($todayStart, $todayEnd) {
                    $q->where('event_id', TransactionEvent::PAYMENT_CASH)
                        ->where('timestamp', '>=', $todayStart)
                        ->where('timestamp', '<=', $todayEnd);
                })
                ->cursor()
                ->each(function (Invoice $invoice) use ($startDate, $endDate) {
                    (new InvoiceTransactionEventEntryCash())->run($invoice, $startDate, $endDate);
                });

    }

}