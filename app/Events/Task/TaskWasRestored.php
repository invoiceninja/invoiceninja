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

namespace App\Events\Task;

use App\Models\Company;
use App\Models\Task;
use Illuminate\Queue\SerializesModels;

/**
 * Class TaskWasRestored.
 */
class TaskWasRestored
{
    use SerializesModels;

    /**
     * @var Task
     */
    public $task;

    public $company;

    public $event_vars;

    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param Task $task
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Task $task, $fromDeleted, Company $company, array $event_vars)
    {
        $this->task = $task;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
