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

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Project;
use App\Models\Webhook;

class ProjectObserver
{
    public $afterCommit = true;

    /**
     * Handle the product "created" event.
     *
     * @param Project $project
     * @return void
     */
    public function created(Project $project)
    {
        $subscriptions = Webhook::where('company_id', $project->company_id)
                            ->where('event_id', Webhook::EVENT_PROJECT_CREATE)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_PROJECT_CREATE, $project, $project->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the product "updated" event.
     *
     * @param Project $project
     * @return void
     */
    public function updated(Project $project)
    {
        $event = Webhook::EVENT_PROJECT_UPDATE;

        if ($project->getOriginal('deleted_at') && !$project->deleted_at) {
            $event = Webhook::EVENT_RESTORE_PROJECT;
        }

        if ($project->is_deleted) {
            $event = Webhook::EVENT_PROJECT_DELETE;
        }


        $subscriptions = Webhook::where('company_id', $project->company_id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $project, $project->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the product "deleted" event.
     *
     * @param Project $project
     * @return void
     */
    public function deleted(Project $project)
    {
        if ($project->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $project->company_id)
                            ->where('event_id', Webhook::EVENT_ARCHIVE_PROJECT)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_PROJECT, $project, $project->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the product "restored" event.
     *
     * @param Project $project
     * @return void
     */
    public function restored(Project $project)
    {
        //
    }

    /**
     * Handle the product "force deleted" event.
     *
     * @param Project $project
     * @return void
     */
    public function forceDeleted(Project $project)
    {
        //
    }
    /**
     * Handle the product "archived" event.
     *
     * @param Project $project
     * @return void
     */
}
