<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Task;

use App\Models\Company;
use App\Models\Task;
use Illuminate\Queue\SerializesModels;

/**
 * Class TaskWasArchived.
 */
class TaskWasArchived
{
    use SerializesModels;

    /**
     * @var Task
     */
    public $task;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Task $task
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Task $task, Company $company, array $event_vars)
    {
        $this->task = $task;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
