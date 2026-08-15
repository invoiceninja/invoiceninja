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

namespace App\Repositories;

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\Project;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

/**
 * Class for project repository.
 */
class ProjectRepository extends BaseRepository
{
    use GeneratesCounter;

    public function save(array $data, Project $project): ?Project
    {
        $tag_ids = $this->resolveTagIdsForSync($data, $project);
        $is_new_project = ! $project->exists;

        $project->fill($data);

        if (! $is_new_project && empty($project->number)) {
            $project = $this->saveProjectNumber($project);
        } else {
            $project->saveQuietly();
        }

        if ($is_new_project && empty($project->number)) {
            $project = $this->saveProjectNumber($project);
        }

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $project, array_key_exists('is_public', $data) ? (bool) $data['is_public'] : null);
        }

        $this->syncResolvedTags($project, $tag_ids);

        return $project;
    }

    private function saveProjectNumber(Project $project): Project
    {
        for ($attempt = 1; $attempt <= 50; $attempt++) {
            try {
                $project->number = $this->getNextProjectNumber($project);
                $project->saveQuietly();

                return $project;
            } catch (QueryException $e) {
                if (! $this->isDuplicateNumberException($e) || $attempt === 50) {
                    throw $e;
                }
            }
        }

        return $project;
    }

    private function isDuplicateNumberException(QueryException $e): bool
    {
        $sql_state = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driver_code = (string) ($e->errorInfo[1] ?? '');
        $message = strtolower($e->getMessage());

        return $sql_state === '23505'
            || $driver_code === '1062'
            || $driver_code === '2067'
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'unique violation');
    }

    /**
     * Invoices a collection of projects into a single invoice.
     *
     * @param  mixed $projects
     * @return Invoice $invoice
     */
    public function invoice(mixed $projects)
    {
        $_project = $projects->first();

        /** @var Invoice $invoice */
        $invoice = InvoiceFactory::create($_project->company_id, $_project->user_id);
        $invoice->client_id = $_project->client_id;

        if (count($projects) == 1) {
            $invoice->project_id = $_project->id;
        }
        // $invoice->project_id = $project->id;

        $lines = [];

        foreach ($projects as $project) {
            $project->tasks()
                    ->withTrashed()
                    ->whereNull('invoice_id')
                    ->where('is_deleted', 0)
                    ->cursor()
                    ->each(function ($task, $key) use (&$lines) {

                        if (!$task->isRunning() && $task->calcDuration(true) > 0) {
                            if ($key == 0 && $task->company->invoice_task_project) {
                                $body = '<div class="project-header">' . $task->project->name . '</div>' . $task->project?->public_notes ?? ''; //@phpstan-ignore-line
                                $body .= '<div class="task-time-details">' . $task->description() . '</div>';
                            } elseif (!$task->company->invoice_task_hours && !$task->company->invoice_task_timelog && !$task->company->invoice_task_datelog && !$task->company->invoice_task_item_description) {
                                $body = $task->description ?? '';
                            } else {
                                $body = '<div class="task-time-details">' . $task->description() . '</div>';
                            }

                            $item = new InvoiceItem();
                            $item->quantity = $task->getQuantity();
                            $item->cost = $task->getRate();
                            $item->product_key = '';
                            $item->notes = $body;
                            $item->task_id = $task->hashed_id;
                            $item->tax_id = (string) Product::PRODUCT_TYPE_SERVICE;
                            $item->type_id = '2';
                            $item->custom_value1 = $task->custom_value1;
                            $item->custom_value2 = $task->custom_value2;
                            $item->custom_value3 = $task->custom_value3;
                            $item->custom_value4 = $task->custom_value4;
                            $lines[] = $item;
                        }

                    });

            $project->expenses()
                ->withTrashed()
                ->where('should_be_invoiced', true)
                ->whereNull('payment_date')
                ->cursor()
                ->each(function ($expense) use (&$lines) {

                    $item = new InvoiceItem();
                    $item->quantity = 1;
                    $item->cost = $expense->foreign_amount > 0 ? $expense->foreign_amount : $expense->amount;
                    $item->product_key = $expense->category()->exists() ? $expense->category->name : '';
                    $item->notes = $expense->public_notes ?? '';
                    $item->line_total = round($item->cost * $item->quantity, 2);
                    $item->tax_name1 = $expense->tax_name1;
                    $item->tax_rate1 = $expense->calculatedTaxRate($expense->tax_amount1, $expense->tax_rate1);
                    $item->tax_name2 = $expense->tax_name2;
                    $item->tax_rate2 = $expense->calculatedTaxRate($expense->tax_amount2, $expense->tax_rate2);
                    $item->tax_name3 = $expense->tax_name3;
                    $item->tax_rate3 = $expense->calculatedTaxRate($expense->tax_amount3, $expense->tax_rate3);
                    $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
                    $item->expense_id = $expense->hashed_id;
                    $item->type_id = '1';
                    $item->custom_value1 = $expense->custom_value1;
                    $item->custom_value2 = $expense->custom_value2;
                    $item->custom_value3 = $expense->custom_value3;
                    $item->custom_value4 = $expense->custom_value4;

                    $lines[] = $item;
                });

        }

        $invoice->uses_inclusive_taxes = $project->company->settings->inclusive_taxes ?? false;
        $invoice->line_items = $lines;

        return $invoice;

    }
}
