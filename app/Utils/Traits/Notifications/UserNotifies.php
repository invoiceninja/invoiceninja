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

namespace App\Utils\Traits\Notifications;

/**
 * Class UserNotifies.
 *
 * I think the term $required_permissions is confusing here, what
 * we are actually defining is the notifications available on the 
 * user itself.
 */
trait UserNotifies
{
    public function findUserNotificationTypes($invitation, $company_user, $entity_name, $required_permissions) :array
    {
        if ($company_user->company->is_disabled) {
            return [];
        }

        $notifiable_methods = [];
        $notifications = $company_user->notifications;

        //if a user owns this record or is assigned to it, they are attached the permission for notification.
        if ($invitation->{$entity_name}->user_id == $company_user->_user_id || $invitation->{$entity_name}->assigned_user_id == $company_user->user_id) {
            array_push($required_permissions, 'all_user_notifications');
        }

        if (count(array_intersect($required_permissions, $notifications->email)) >= 1 || count(array_intersect($required_permissions, 'all_user_notifications')) >= 1 || count(array_intersect($required_permissions, 'all_notifications')) >= 1) {
            array_push($notifiable_methods, 'mail');
        }

        // if(count(array_intersect($required_permissions, $notifications->slack)) >=1)
        //     array_push($notifiable_methods, 'slack');

        return $notifiable_methods;
    }

    public function findUserEntityNotificationType($entity, $company_user, $required_permissions) :array
    {
        if ($company_user->company->is_disabled) {
            return [];
        }

        $notifiable_methods = [];
        $notifications = $company_user->notifications;

        if (! $notifications) {
            return [];
        }

        if ($entity->user_id == $company_user->_user_id || $entity->assigned_user_id == $company_user->user_id) {
            array_push($required_permissions, 'all_user_notifications');
        }

        if (count(array_intersect($required_permissions, $notifications->email)) >= 1 || count(array_intersect($required_permissions, ['all_user_notifications'])) >= 1 || count(array_intersect($required_permissions, ['all_notifications'])) >= 1) {
            array_push($notifiable_methods, 'mail');
        }

        return $notifiable_methods;
    }

    public function findCompanyUserNotificationType($company_user, $required_permissions) :array
    {

        if ($company_user->company->is_disabled) {
            return [];
        }

        $notifiable_methods = [];
        $notifications = $company_user->notifications;

        //conditional to define whether the company user has the required notification for the MAIL notification TYPE
        if (count(array_intersect($required_permissions, $notifications->email)) >= 1 || count(array_intersect($required_permissions, ['all_user_notifications'])) >= 1 || count(array_intersect($required_permissions, ['all_notifications'])) >= 1) {
            array_push($notifiable_methods, 'mail');
        }

        return $notifiable_methods;
    }

    /*
     * Returns a filtered collection of users with the
     * required notification - NOTE this is only implemented for 
     * EMAIL notification types - we'll need to chain
     * additional types at a later stage.
     */
    public function filterUsersByPermissions($company_users, $entity, array $required_notification)
    {

        return $company_users->filter(function($company_user) use($required_notification, $entity){

            return $this->checkNotificationExists($company_user, $entity, $required_notification);

        });

    }

    private function checkNotificationExists($company_user, $entity, $required_notification)
    {
        /* Always make sure we push the `all_notificaitons` into the mix */
        array_push($required_notification, 'all_notifications');

        /* Selectively add the all_user if the user is associated with the entity */
        if ($entity->user_id == $company_user->_user_id || $entity->assigned_user_id == $company_user->user_id) 
            array_push($required_notification, 'all_user_notifications');
        

        return count(array_intersect($required_notification, $company_user->notifications->email)) >= 1;
    }
}
