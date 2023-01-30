<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Scheduler;

use App\Models\Client;
use App\Models\RecurringInvoice;
use App\Models\Scheduler;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;

class SchedulerService
{
    use MakesHash;
    use MakesDates;

    private string $method;

    private Client $client;

    public function __construct(public Scheduler $scheduler) {}

    /**
     * Called from the TaskScheduler Cron
     * 
     * @return void 
     */
    public function runTask(): void
    {
        $this->{$this->scheduler->template}();
    }

    private function client_statement()
    {   
        $query = Client::query()
                        ->where('company_id', $this->scheduler->company_id)
                        ->where('is_deleted',0);

        //Email only the selected clients
        if(count($this->scheduler->parameters['clients']) >= 1)
            $query->whereIn('id', $this->transformKeys($this->scheduler->parameters['clients']));
     
        $query->cursor()
            ->each(function ($_client){

            $this->client = $_client;

           //work out the date range 
            $statement_properties = $this->calculateStatementProperties();

            $_client->service()->statement($statement_properties,true);

        });

        //calculate next run dates;
        $this->calculateNextRun();
    
    }

    /**
     * Hydrates the array needed to generate the statement
     * 
     * @return array The statement options array
     */
    private function calculateStatementProperties(): array
    {
        $start_end = $this->calculateStartAndEndDates();

        return [
            'start_date' =>$start_end[0], 
            'end_date' =>$start_end[1], 
            'show_payments_table' => $this->scheduler->parameters['show_payments_table'], 
            'show_aging_table' => $this->scheduler->parameters['show_aging_table'], 
            'status' => $this->scheduler->parameters['status']
        ];

    }

    /**
     * Start and end date of the statement
     * 
     * @return array [$start_date, $end_date];
     */
    private function calculateStartAndEndDates(): array
    {
        return match ($this->scheduler->parameters['date_range']) {
            'this_month' => [now()->firstOfMonth()->format('Y-m-d'), now()->lastOfMonth()->format('Y-m-d')],
            'this_quarter' => [now()->firstOfQuarter()->format('Y-m-d'), now()->lastOfQuarter()->format('Y-m-d')],
            'this_year' => [now()->firstOfYear()->format('Y-m-d'), now()->lastOfYear()->format('Y-m-d')],
            'previous_month' => [now()->subMonth()->firstOfMonth()->format('Y-m-d'), now()->subMonth()->lastOfMonth()->format('Y-m-d')],
            'previous_quarter' => [now()->subQuarter()->firstOfQuarter()->format('Y-m-d'), now()->subQuarter()->lastOfQuarter()->format('Y-m-d')],
            'previous_year' => [now()->subYear()->firstOfYear()->format('Y-m-d'), now()->subYear()->lastOfYear()->format('Y-m-d')],
            'custom_range' => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']],
             default => [now()->firstOfMonth()->format('Y-m-d'), now()->lastOfMonth()->format('Y-m-d')],
        };
    }


    /**
     * Sets the next run date of the scheduled task
     * 
     */
    private function calculateNextRun()
    {
        if (! $this->scheduler->next_run) {
            return null;
        }

        $offset = $this->scheduler->company->timezone_offset();

        switch ($this->scheduler->frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                $next_run = now()->startOfDay()->addDay();
                break;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $next_run = now()->startOfDay()->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $next_run = now()->startOfDay()->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $next_run = now()->startOfDay()->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $next_run = now()->startOfDay()->addMonthNoOverflow();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $next_run = now()->startOfDay()->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $next_run = now()->startOfDay()->addYears(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                $next_run = now()->startOfDay()->addYears(3);
                break;
            default:
                $next_run =  null;
        }


        $this->scheduler->next_run_client = $next_run ?: null; 
        $this->scheduler->next_run = $next_run ? $next_run->copy()->addSeconds($offset) : null;
        $this->scheduler->save();

    }

    //handle when the scheduler has been paused.


}