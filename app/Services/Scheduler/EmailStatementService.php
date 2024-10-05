<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Scheduler;

use App\DataMapper\Schedule\EmailStatement;
use App\Models\Client;
use App\Models\Scheduler;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;

class EmailStatementService
{
    use MakesHash;
    use MakesDates;

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
        } else {
            $query->where('balance', '>', 0);
        }

        $query->cursor()
            ->each(function ($_client) {

                //work out the date range
                $statement_properties = $this->calculateStatementProperties($_client);

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
    private function calculateStatementProperties(Client $client): array
    {
        $start_end = $this->calculateStartAndEndDates($client);

        return [
            'start_date' => $start_end[0],
            'end_date' => $start_end[1],
            'show_payments_table' => $this->scheduler->parameters['show_payments_table'] ?? true,
            'show_aging_table' => $this->scheduler->parameters['show_aging_table'] ?? true,
            'show_credits_table' => $this->scheduler->parameters['show_credits_table'] ?? true,
            'only_clients_with_invoices' => $this->scheduler->parameters['only_clients_with_invoices'] ?? false,
            'status' => $this->scheduler->parameters['status'] ?? 'all',
        ];
    }

    /**
     * Start and end date of the statement
     *
     * @return array [$start_date, $end_date];
     */
    private function calculateStartAndEndDates(Client $client): array
    {
        return match ($this->scheduler->parameters['date_range']) {
            EmailStatement::LAST7 => [now()->startOfDay()->subDays(7)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST30 => [now()->startOfDay()->subDays(30)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST365 => [now()->startOfDay()->subDays(365)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::THIS_MONTH => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::LAST_MONTH => [now()->startOfDay()->subMonthNoOverflow()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->subMonthNoOverflow()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::THIS_QUARTER => [now()->startOfDay()->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->endOfQuarter()->format('Y-m-d')],
            EmailStatement::LAST_QUARTER => [now()->startOfDay()->subQuarterNoOverflow()->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuarterNoOverflow()->endOfQuarter()->format('Y-m-d')],
            EmailStatement::THIS_YEAR => [now()->startOfDay()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->lastOfYear()->format('Y-m-d')],
            EmailStatement::LAST_YEAR => [now()->startOfDay()->subYearNoOverflow()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->subYearNoOverflow()->lastOfYear()->format('Y-m-d')],
            EmailStatement::ALL_TIME => [
                $client->invoices()->selectRaw('MIN(invoices.date) as start_date')->pluck('start_date')->first()
                    ?: Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            EmailStatement::CUSTOM_RANGE => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']],
            default => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
        };
    }

}
