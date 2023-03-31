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
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use App\DataMapper\Schedule\EmailStatement;

class EmailStatementService
{
    use MakesHash;
    
    private Client $client;

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {
        $query = Client::query()
                ->where('company_id', $this->scheduler->company_id)
                ->where('is_deleted', 0);

        //Email only the selected clients
        if (count($this->scheduler->parameters['clients']) >= 1) {
            $query->whereIn('id', $this->transformKeys($this->scheduler->parameters['clients']));
        }
     
        $query->cursor()
            ->each(function ($_client) {
                $this->client = $_client;

                //work out the date range
                $statement_properties = $this->calculateStatementProperties();

                $_client->service()->statement($statement_properties, true);
            });

        //calculate next run dates;
        $this->scheduler->calculateNextRun();
        
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
            EmailStatement::LAST7 => [now()->startOfDay()->subDays(7)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST30 => [now()->startOfDay()->subDays(30)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST365 => [now()->startOfDay()->subDays(365)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::THIS_MONTH => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::LAST_MONTH => [now()->startOfDay()->subMonthNoOverflow()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->subMonthNoOverflow()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::THIS_QUARTER => [now()->startOfDay()->firstOfQuarter()->format('Y-m-d'), now()->startOfDay()->lastOfQuarter()->format('Y-m-d')],
            EmailStatement::LAST_QUARTER => [now()->startOfDay()->subQuarterNoOverflow()->firstOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuarterNoOverflow()->lastOfQuarter()->format('Y-m-d')],
            EmailStatement::THIS_YEAR => [now()->startOfDay()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->lastOfYear()->format('Y-m-d')],
            EmailStatement::LAST_YEAR => [now()->startOfDay()->subYearNoOverflow()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->subYearNoOverflow()->lastOfYear()->format('Y-m-d')],
            EmailStatement::CUSTOM_RANGE => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']],
            default => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
        };
    }

}
