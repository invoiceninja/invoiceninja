<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Queue\SerializesModels;

class LogRequestSending
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

    public function handle(RequestSending $event)
    {
        nlog("Request");
        nlog($event->request->headers());
        nlog($event->request->url());
        nlog(json_encode($event->request->headers()));
        nlog($event->request->body());

    }
}
