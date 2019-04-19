<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Queue\SerializesModels;

/**
 * Class TaskWasDeleted.
 */
class TaskWasDeleted extends Event
{
    use SerializesModels;

    /**
     * @var Task
     */
    public $task;

    /**
     * Create a new event instance.
     *
     * @param Task $task
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }
}
