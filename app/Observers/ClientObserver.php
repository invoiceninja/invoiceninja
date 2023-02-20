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
        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', Webhook::EVENT_CREATE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_CLIENT, $client, $client->company)->delay(0);
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
        $event = Webhook::EVENT_UPDATE_CLIENT;

        if ($client->getOriginal('deleted_at') && !$client->deleted_at) {
            $event = Webhook::EVENT_RESTORE_CLIENT;
        }
        
        if ($client->is_deleted) {
            $event = Webhook::EVENT_DELETE_CLIENT;
        }
        
        
        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $client, $client->company, 'client')->delay(0);
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
        if ($client->is_deleted) {
            return;
        }
        
        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', Webhook::EVENT_ARCHIVE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_CLIENT, $client, $client->company)->delay(0);
        }
    }
}
