<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\User;

use App\Utils\Ninja;
use App\Models\Company;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use App\Jobs\Util\SystemLogger;
use App\Mail\User\UserLoggedIn;
use App\Jobs\Mail\NinjaMailerJob;
use Illuminate\Support\Facades\App;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use App\Notifications\Ninja\GenericNinjaAdminNotification;

class UpdateUserLoginFailed implements ShouldQueue
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
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

        $user = MultiDB::hasUser(['email' => $event->email]);

        if(!$user)
            return;

        $user->increment('failed_logins',1);

        if($user->failed_logins > 3) {
            $content = [
                "Multiple Logins failed for user: {$user->email}",
                "IP address: {$event->ip}",
            ];

            $company = Company::first();
            $company->notification(new GenericNinjaAdminNotification($content))->ninja();

        }

    }
}
