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
use App\Models\Client;
use App\Models\Webhook;

class ClientObserver
{
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
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_CLIENT, $client, $client->company);
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
        $subscriptions = Webhook::where('company_id', $client->company->id)
                                    ->where('event_id', Webhook::EVENT_UPDATE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_UPDATE_CLIENT, $client, $client->company);
        }
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param Client $client
     * @return void
     */
    public function deleted(Client $client)
    {
        $subscriptions = Webhook::where('company_id', $client->company->id)
                                    ->where('event_id', Webhook::EVENT_DELETE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_DELETE_CLIENT, $client, $client->company);
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
        //
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
