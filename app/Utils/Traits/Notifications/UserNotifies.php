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

        if (count(array_intersect($required_permissions, $notifications->email)) >= 1 || count(array_intersect($required_permissions, ['all_user_notifications'])) >= 1 || count(array_intersect($required_permissions, ['all_notifications'])) >= 1) {
            array_push($notifiable_methods, 'mail');
        }

        return $notifiable_methods;
    }
}
