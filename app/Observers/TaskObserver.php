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

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Task;
use App\Models\Webhook;

class TaskObserver
{
    public $afterCommit = true;

    /**
     * Handle the task "created" event.
     *
     * @param Task $task
     * @return void
     */
    public function created(Task $task)
    {
        $subscriptions = Webhook::where('company_id', $task->company_id)
                        ->where('event_id', Webhook::EVENT_CREATE_TASK)
                        ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_TASK, $task, $task->company)->delay(0);
        }
    }

    /**
     * Handle the task "updated" event.
     *
     * @param Task $task
     * @return void
     */
    public function updated(Task $task)
    {
        $event = Webhook::EVENT_UPDATE_TASK;

        if ($task->getOriginal('deleted_at') && !$task->deleted_at) {
            $event = Webhook::EVENT_RESTORE_TASK;
        }

        if ($task->is_deleted) {
            $event = Webhook::EVENT_DELETE_TASK;
        }

        $subscriptions = Webhook::where('company_id', $task->company_id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $task, $task->company)->delay(0);
        }
    }

    /**
     * Handle the task "deleted" event.
     *
     * @param Task $task
     * @return void
     */
    public function deleted(Task $task)
    {
        if ($task->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $task->company_id)
                        ->where('event_id', Webhook::EVENT_ARCHIVE_TASK)
                        ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_TASK, $task, $task->company)->delay(0);
        }

    }

    /**
     * Handle the task "restored" event.
     *
     * @param Task $task
     * @return void
     */
    public function restored(Task $task)
    {
        //
    }

    /**
     * Handle the task "force deleted" event.
     *
     * @param Task $task
     * @return void
     */
    public function forceDeleted(Task $task)
    {
        //
    }
}
