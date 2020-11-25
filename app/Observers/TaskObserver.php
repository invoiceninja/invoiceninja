<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Task;
use App\Models\Webhook;

class TaskObserver
{
    /**
     * Handle the task "created" event.
     *
     * @param Task $task
     * @return void
     */
    public function created(Task $task)
    {

        $subscriptions = Webhook::where('company_id', $task->company->id)
                        ->where('event_id', Webhook::EVENT_CREATE_TASK)
                        ->exists();

        if($subscriptions)
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_TASK, $task, $task->company);
    }

    /**
     * Handle the task "updated" event.
     *
     * @param Task $task
     * @return void
     */
    public function updated(Task $task)
    {

        $subscriptions = Webhook::where('company_id', $task->company->id)
                        ->where('event_id', Webhook::EVENT_UPDATE_TASK)
                        ->exists();

        if($subscriptions)
            WebhookHandler::dispatch(Webhook::EVENT_UPDATE_TASK, $task, $task->company);
    }

    /**
     * Handle the task "deleted" event.
     *
     * @param Task $task
     * @return void
     */
    public function deleted(Task $task)
    {


        $subscriptions = Webhook::where('company_id', $task->company->id)
                        ->where('event_id', Webhook::EVENT_DELETE_TASK)
                        ->exists();

        if($subscriptions)
            WebhookHandler::dispatch(Webhook::EVENT_DELETE_TASK, $task, $task->company);
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
