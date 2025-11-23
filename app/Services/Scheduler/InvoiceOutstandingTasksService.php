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

namespace App\Services\Scheduler;

use Carbon\Carbon;
use App\Models\Task;
use App\Models\Client;
use App\Models\Product;
use App\Models\Scheduler;
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\DataMapper\Schedule\EmailStatement;
use App\Repositories\InvoiceRepository;

class InvoiceOutstandingTasksService
{
    use MakesHash;
    use MakesDates;

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {

        // $query = Task::query()
        //     ->where('company_id', $this->scheduler->company_id)
        //     ->where('is_deleted', 0);

        // if (count($this->scheduler->parameters['clients']) >= 1) {
        //     $query->whereIn('client_id', $this->transformKeys($this->scheduler->parameters['clients']));
        // }

        // if (!$this->scheduler->parameters['include_project_tasks']) {
        //     $query->whereNull('project_id');
        // }
        $query = Client::query()
            ->where('company_id', $this->scheduler->company_id)
            ->where('is_deleted', 0);

        if (count($this->scheduler->parameters['clients']) >= 1) {
            $query->whereIn('id', $this->transformKeys($this->scheduler->parameters['clients']));
        }

        $query->whereHas('tasks', function ($sub_query) {
            $sub_query->whereNull('invoice_id')
                    ->where('is_deleted', 0)
                    ->whereBetween('calculated_start_date', $this->calculateStartAndEndDates())
                    ->when(!$this->scheduler->parameters['include_project_tasks'], function ($sub_query_two) {
                        $sub_query_two->whereNull('project_id');
                    });
        });

        $invoice_repo = new InvoiceRepository();

        $query->cursor()
                ->each(function (Client $client) use ($invoice_repo) {

                    $line_items = $client->tasks()->whereNull('invoice_id')
                        ->where('is_deleted', 0)
                        ->whereBetween('calculated_start_date', $this->calculateStartAndEndDates())
                        ->when(!$this->scheduler->parameters['include_project_tasks'], function ($sub_query_two) {
                            return $sub_query_two->whereNull('project_id');
                        })
                        ->get()
                        ->filter(function (Task $task) {
                            return $task->calcDuration(true) > 0 && !$task->isRunning();
                        })
                        ->map(function (Task $task, $key) {

                            if ($key == 0 && $task->company->invoice_task_project) {
                                $body = '<div class="project-header">'.$task->project->name.'</div>' .$task->project?->public_notes ?? ''; //@phpstan-ignore-line
                                $body .= '<div class="task-time-details">'.$task->description().'</div>';
                            } elseif (!$task->company->invoice_task_hours && !$task->company->invoice_task_timelog && !$task->company->invoice_task_datelog && !$task->company->invoice_task_item_description) {
                                $body = $task->description ?? '';
                            } else {
                                $body = '<div class="task-time-details">'.$task->description().'</div>';
                            }

                            $item = new InvoiceItem();
                            $item->quantity = $task->getQuantity();
                            $item->cost = $task->getRate();
                            $item->product_key = '';
                            $item->notes = $body;
                            $item->task_id = $task->hashed_id;
                            $item->tax_id = (string) Product::PRODUCT_TYPE_SERVICE;
                            $item->type_id = '2';

                            return $item;

                        })
                        ->toArray();

                    if (count(array_values($line_items)) > 0) {

                        $data = [
                            'client_id' => $client->id,
                            'date' => now()->addSeconds($client->company->utc_offset())->format('Y-m-d'),
                            'line_items' => array_values($line_items),
                            'uses_inclusive_taxes' => $client->company->settings->inclusive_taxes ?? false,
                        ];

                        $invoice = $invoice_repo->save($data, InvoiceFactory::create($client->company_id, $client->user_id));

                        if ($this->scheduler->parameters['auto_send']) {
                            nlog('sending email');
                            $invoice->service()->sendEmail();
                        }
                    }
                });

        $this->scheduler->calculateNextRun();

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
            EmailStatement::THIS_QUARTER => [now()->startOfDay()->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->endOfQuarter()->format('Y-m-d')],
            EmailStatement::LAST_QUARTER => [now()->startOfDay()->subQuarterNoOverflow()->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuarterNoOverflow()->endOfQuarter()->format('Y-m-d')],
            EmailStatement::THIS_YEAR => [now()->startOfDay()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->lastOfYear()->format('Y-m-d')],
            EmailStatement::LAST_YEAR => [now()->startOfDay()->subYearNoOverflow()->firstOfYear()->format('Y-m-d'), now()->startOfDay()->subYearNoOverflow()->lastOfYear()->format('Y-m-d')],
            EmailStatement::ALL_TIME => [
                Task::query() //@phpstan-ignore-line
                    ->where('company_id', $this->scheduler->company_id)
                    ->where('is_deleted', 0)
                    ->selectRaw('MIN(tasks.calculated_start_date) as start_date')
                    ->pluck('start_date')
                    ->first()
                    ?: Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            EmailStatement::CUSTOM_RANGE => [$this->scheduler->parameters['start_date'], $this->scheduler->parameters['end_date']],
            default => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
        };
    }
}
