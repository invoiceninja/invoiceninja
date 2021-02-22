<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\VerifyUserObject;
use App\Notifications\Ninja\VerifyUser;
use App\Utils\Ninja;
use Exception;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendVerificationNotification implements ShouldQueue
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

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDB($event->company->db);

        try {

            $nmo = new NinjaMailerObject;
            $nmo->mailable = new NinjaMailer((new VerifyUserObject($event->user, $event->company))->build());
            $nmo->company = $event->company;
            $nmo->to_user = $event->user;
            $nmo->settings = $event->company->settings;

            NinjaMailerJob::dispatch($nmo);

            // $event->user->notify(new VerifyUser($event->user, $event->company));

            Ninja::registerNinjaUser($event->user);
            
        } catch (Exception $e) {
            nlog("I couldn't send the email " . $e->getMessage());
        }
    }
}
