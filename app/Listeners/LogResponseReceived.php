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

use App\Utils\Ninja;
use App\Libraries\MultiDB;
use App\Mail\User\UserAdded;
use Illuminate\Support\Carbon;
use App\Jobs\Mail\NinjaMailerJob;
use Illuminate\Support\Facades\App;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Http\Client\Events\ResponseReceived;

class LogResponseReceived
{

        use Dispatchable, InteractsWithSockets, SerializesModels;

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
