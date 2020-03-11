<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Misc;

use App\Notifications\Admin\EntityViewedNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class InvitationViewedListener implements ShouldQueue
{
    use UserNotifies;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(){}

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $entity_name = $event->entity;
        $invitation = $event->invitation;

        $notification = new EntityViewedNotification($invitation, $entity_name);

        foreach($invitation->company->company_users as $company_user)
        {

            $entity_viewed = "{$entity_name}_viewed";

            $notification->method = $this->findUserNotificationTypes($invitation, $company_user, $entity_name, ['all_notifications', $entity_viewed]);

            $company_user->user->notify($notification);
        }

        if(isset($invitation->company->slack_webhook_url)){

            $notification->method = ['slack'];

            Notification::route('slack', $invitation->company->slack_webhook_url)
                        ->notify($notification);

        }
    }



    private function userNotificationArray($notifications)
    {
        $via_array = [];

        if(stripos($this->company_user->permissions, ) !== false);

    }

}
