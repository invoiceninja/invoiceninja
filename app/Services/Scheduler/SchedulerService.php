<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Scheduler;

use App\Models\Client;
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

class SchedulerService
{
    use MakesHash;

    private string $method;

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
                        ->where('company_id', $this->scheduler->company_id);

        //Email only the selected clients
        if(count($this->scheduler->parameters['clients']) >= 1)
            $query->where('id', $this->transformKeys($this->scheduler->parameters['clients']));
     
        $statement_properties = $this->calculateStatementProperties();

        $query->cursor()
            ->each(function ($client) use($statement_properties){

           //work out the date range 
            $pdf = $client->service()->statement($statement_properties);

        });

    }

    private function calculateStatementProperties()
    {
        $start_end = $this->calculateStartAndEndDates();

        return [
            'start_date' =>$start_end[0], 
            'end_date' =>$start_end[1], 
            'show_payments_table' => $this->scheduler->parameters['show_payments_table'], 
            'show_aging_table' => $this->scheduler->parameters['show_aging_table'], 
            'status' => $this->scheduler->status
        ];

    }

    private function calculateStartAndEndDates()
    {
        return match ($this->scheduler->parameters['date_range']) {
            'this_month' => [now()->firstOfMonth()->format('Y-m-d'), now()->lastOfMonth()->format('Y-m-d')],
            'this_quarter' => [now()->firstOfQuarter()->format('Y-m-d'), now()->lastOfQuarter()->format('Y-m-d')],
            'this_year' => [now()->firstOfYear()->format('Y-m-d'), now()->format('Y-m-d')],
            'previous_month' => [now()->subMonth()->firstOfMonth()->format('Y-m-d'), now()->subMonth()->lastOfMonth()->format('Y-m-d')],
            'previous_quarter' => [now()->subQuarter()->firstOfQuarter()->format('Y-m-d'), now()->subQuarter()->lastOfQuarter()->format('Y-m-d')],
            'previous_year' => [now()->subYear()->firstOfYear()->format('Y-m-d'), now()->subYear()->format('Y-m-d')],
            'custom_range' => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']]
        };
    }

    private function thisMonth()
    {

    }
}