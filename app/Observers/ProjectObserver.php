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
            WebhookHandler::dispatch(Webhook::EVENT_PROJECT_CREATE, $project, $project->company, 'client')->delay(now()->addSeconds(2));
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
        $subscriptions = Webhook::where('company_id', $project->company_id)
                            ->where('event_id', Webhook::EVENT_PROJECT_UPDATE)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_PROJECT_UPDATE, $project, $project->company, 'client')->delay(now()->addSeconds(2));
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
        //
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
}
