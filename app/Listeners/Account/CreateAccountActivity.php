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

namespace App\Listeners\Account;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Utils\Ninja;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateAccountActivity implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activity_repo
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
        MultiDB::setDb($event->company->db);

        if (Ninja::isHosted()) {
            $nmo = new NinjaMailerObject();
            $nmo->mailable = new \Modules\Admin\Mail\Welcome($event->user);
            $nmo->company = $event->company;
            $nmo->settings = $event->company->settings;
            $nmo->to_user = $event->user;

            NinjaMailerJob::dispatch($nmo, true);
        }
    }
}
