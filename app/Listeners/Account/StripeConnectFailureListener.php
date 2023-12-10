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

namespace App\Listeners\Account;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Ninja\StripeConnectFailed;
use App\Utils\Ninja;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class StripeConnectFailureListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->db);

        if (Ninja::isHosted() && is_null(Cache::get("stripe_connect_notification:{$event->company->company_key}"))) {

            $nmo = new NinjaMailerObject();
            $nmo->mailable = new StripeConnectFailed($event->company->owner(), $event->company);
            $nmo->company = $event->company;
            $nmo->settings = $event->company->settings;
            $nmo->to_user = $event->company->owner();

            NinjaMailerJob::dispatch($nmo, true);

            Cache::put("stripe_connect_notification:{$event->company->company_key}", true, 60 * 60 * 24);

        }
    }
}
