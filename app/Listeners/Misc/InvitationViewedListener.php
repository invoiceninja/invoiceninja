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

namespace App\Listeners\Misc;

use App\Jobs\Mail\EntityViewedMailer;
use App\Libraries\MultiDB;
use App\Notifications\Admin\EntityViewedNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class InvitationViewedListener implements ShouldQueue
{
    use UserNotifies;

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

        $entity_name = lcfirst(class_basename($event->entity));
        $invitation = $event->invitation;

        $notification = new EntityViewedNotification($invitation, $entity_name);

        foreach ($invitation->company->company_users as $company_user) {
            $entity_viewed = "{$entity_name}_viewed";

            $methods = $this->findUserNotificationTypes($invitation, $company_user, $entity_name, ['all_notifications', $entity_viewed]);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                EntityViewedMailer::dispatch($invitation, $entity_name, $company_user->user, $invitation->company);
            }

            $notification->method = $methods;

            $company_user->user->notify($notification);
        }

        if (isset($invitation->company->slack_webhook_url)) {
            $notification->method = ['slack'];

            Notification::route('slack', $invitation->company->slack_webhook_url)
                        ->notify($notification);
        }
    }
}
