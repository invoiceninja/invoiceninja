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

namespace App\Transformers;

use App\Models\Task;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Project;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;

/**
 * class ProjectTransformer.
 */
class ProjectTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'client',
        'tasks',
        'invoices',
        'expenses',
        'quotes',
    ];

    public function includeDocuments(Project $project)
    {
        $transformer = new DocumentTransformer($this->serializer);

        // if($project->documents->count() > 0) {
        return $this->includeCollection($project->documents, $transformer, Document::class);
        // }

        // return null;
    }

    public function includeClient(Project $project): ?\League\Fractal\Resource\Item
    {

        if (!$project->client) {
            nlog("Project {$project->hashed_id} does not have a client attached - this project is in a bad state");
            return null;
        }

        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($project->client, $transformer, Client::class);
    }

    public function includeTasks(Project $project): \League\Fractal\Resource\Collection
    {
        $transformer = new TaskTransformer($this->serializer);

        return $this->includeCollection($project->tasks, $transformer, Task::class);
    }

    public function includeInvoices(Project $project): \League\Fractal\Resource\Collection
    {
        $transformer = new InvoiceTransformer($this->serializer);

        return $this->includeCollection($project->invoices, $transformer, Invoice::class);
    }

    public function includeExpenses(Project $project): \League\Fractal\Resource\Collection
    {
        $transformer = new ExpenseTransformer($this->serializer);

        return $this->includeCollection($project->expenses, $transformer, Expense::class);
    }

    public function includeQuotes(Project $project): \League\Fractal\Resource\Collection
    {
        $transformer = new QuoteTransformer($this->serializer);

        return $this->includeCollection($project->quotes, $transformer, Quote::class);
    }

    public function transform(Project $project)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($project->id),
            'user_id' => (string) $this->encodePrimaryKey($project->user_id),
            'assigned_user_id' => (string) $this->encodePrimaryKey($project->assigned_user_id),
            'client_id' => (string) $this->encodePrimaryKey($project->client_id),
            'name' => $project->name ?: '',
            'number' => $project->number ?: '',
            'created_at' => (int) $project->created_at,
            'updated_at' => (int) $project->updated_at,
            'archived_at' => (int) $project->deleted_at,
            'is_deleted' => (bool) $project->is_deleted,
            'task_rate' => (float) $project->task_rate,
            'due_date' => $project->due_date ?: '',
            'private_notes' => (string) $project->private_notes ?: '',
            'public_notes' => (string) $project->public_notes ?: '',
            'budgeted_hours' => (float) $project->budgeted_hours,
            'custom_value1' => (string) $project->custom_value1 ?: '',
            'custom_value2' => (string) $project->custom_value2 ?: '',
            'custom_value3' => (string) $project->custom_value3 ?: '',
            'custom_value4' => (string) $project->custom_value4 ?: '',
            'color' => (string) $project->color ?: '',
            'current_hours' => (int) $project->current_hours ?: 0,
        ];
    }
}
