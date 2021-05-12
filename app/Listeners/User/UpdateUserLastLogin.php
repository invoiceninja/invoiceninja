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

namespace App\Listeners\User;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\User\UserLoggedIn;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateUserLastLogin implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create the event listener.
     *
     * @return void
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

        $user = $event->user;
        $user->last_login = now();
        $user->save();

        $event_vars = $event->event_vars;
        $ip = array_key_exists('ip', $event->event_vars) ? $event->event_vars['ip'] : 'IP address not resolved';

        if($user->ip != $ip)
        {
            $nmo = new NinjaMailerObject;
            $nmo->mailable = new UserLoggedIn($user, $user->account->companies()->first(), $ip);
            $nmo->company = $user->account->companies()->first();
            $nmo->settings = $user->account->companies()->first()->settings;
            $nmo->to_user = $user;
            NinjaMailerJob::dispatch($nmo);
        
            $user->ip = $ip;
            $user->save();
        }


    }
}
