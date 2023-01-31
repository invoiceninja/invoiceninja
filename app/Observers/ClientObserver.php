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
use App\Models\Client;
use App\Models\Webhook;

class ClientObserver
{

    public $afterCommit = true;

    /**
     * Handle the client "created" event.
     *
     * @param Client $client
     * @return void
     */
    public function created(Client $client)
    {
        $subscriptions = Webhook::where('company_id', $client->company->id)
                                    ->where('event_id', Webhook::EVENT_CREATE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_CLIENT, $client, $client->company)->delay(now()->addSeconds(rand(1,5)));
        }
    }

    /**
     * Handle the client "updated" event.
     *
     * @param Client $client
     * @return void
     */
    public function updated(Client $client)
    {

        nlog("updated event {$client->id}");

        $event = Webhook::EVENT_UPDATE_CLIENT;

        if($client->is_deleted)
            $event = Webhook::EVENT_DELETE_CLIENT; //this event works correctly.
        
        $subscriptions = Webhook::where('company_id', $client->company->id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $client, $client->company)->delay(now()->addSeconds(rand(1,5)));
        }

    }

    /**
     * Handle the client "archived" event.
     *
     * @param Client $client
     * @return void
     */
    public function deleted(Client $client)
    {
        if($client->is_deleted)
            return;

        nlog("deleted event {$client->id}");
        
        $subscriptions = Webhook::where('company_id', $client->company->id)
                                    ->where('event_id', Webhook::EVENT_ARCHIVE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_CLIENT, $client, $client->company)->delay(now()->addSeconds(rand(1,5)));
        }
    }

    /**
     * Handle the client "restored" event.
     *
     * @param Client $client
     * @return void
     */
    public function restored(Client $client)
    {
        nlog("Restored {$client->id}");

        $subscriptions = Webhook::where('company_id', $client->company->id)
                                    ->where('event_id', Webhook::EVENT_RESTORE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_RESTORE_CLIENT, $client, $client->company)->delay(now()->addSeconds(rand(1,5)));
        }


        return false;
    }

    /**
     * Handle the client "force deleted" event.
     *
     * @param Client $client
     * @return void
     */
    public function forceDeleted(Client $client)
    {
        //
    }

}
