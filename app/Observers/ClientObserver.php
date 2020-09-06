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
     * @param  \App\Client  $client
     * @return void
     */
    public function created(Client $client)
    {
        WebhookHandler::dispatch(Webhook::EVENT_CREATE_CLIENT, $client);
    }

    /**
     * Handle the client "updated" event.
     *
     * @param  \App\Client  $client
     * @return void
     */
    public function updated(Client $client)
    {
        WebhookHandler::dispatch(Webhook::EVENT_UPDATE_CLIENT, $client);
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param  \App\Client  $client
     * @return void
     */
    public function deleted(Client $client)
    {
        WebhookHandler::dispatch(Webhook::EVENT_DELETE_CLIENT, $client);
    }

    /**
     * Handle the client "restored" event.
     *
     * @param  \App\Client  $client
     * @return void
     */
    public function restored(Client $client)
    {
        //
    }

    /**
     * Handle the client "force deleted" event.
     *
     * @param  \App\Client  $client
     * @return void
     */
    public function forceDeleted(Client $client)
    {
        //
    }
}
