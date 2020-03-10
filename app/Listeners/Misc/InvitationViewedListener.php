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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class InvitationViewedListener implements ShouldQueue
{
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
            $notifiable_methods = [];

            $notifications = $company_user->notifications;

            $entity_viewed = "{$entity_name}_viewed";

            /*** Check for Mail notifications***/
            $all_user_notifications = '';

            if($event->entity->user_id == $company_user->user_id || $event->entity->assigned_user_id == $company_user->user_id)
                $all_user_notifications = "all_user_notifications";

            $possible_permissions = [$entity_viewed, "all_notifications", $all_user_notifications];

            $permission_count = array_intersect($possible_permissions, $notifications->email);

            if(count($permission_count) >=1)
                array_push($notifiable_methods, 'mail');
            /*** Check for Mail notifications***/


            /*** Check for Slack notifications***/
                //@TODO when hillel implements this we can uncomment this.
                // $permission_count = array_intersect($possible_permissions, $notifications->slack);
                // if(count($permission_count) >=1)
                //     array_push($notifiable_methods, 'slack');

            /*** Check for Slack notifications***/

            $notification->method = $notifiable_methods;

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
