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

namespace App\Listeners;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\SerializesModels;

class LogResponseReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function handle(ResponseReceived $event)
    {
        nlog("Request");
        nlog($event->request->headers());
        nlog($event->request->url());
        nlog(json_encode($event->request->headers()));
        nlog($event->request->body());

        nlog("Response");
        nlog($event->response->headers());
        nlog(json_encode($event->response->headers()));
        nlog($event->response->body());
        nlog($event->response->json());
    }
}
