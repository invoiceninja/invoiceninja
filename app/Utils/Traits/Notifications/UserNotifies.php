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

namespace App\Utils\Traits\Notifications;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quote;

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
        $notifiable_methods = [];
        $notifications = $company_user->notifications;

        if ($invitation->company->is_disabled &&
            is_array($notifications->email) ||
            $company_user->trashed() ||
            ! $company_user->user ||
            $company_user->user->trashed()) {
            return [];
        }

        //if a user owns this record or is assigned to it, they are attached the permission for notification.
        if ($invitation->{$entity_name}->user_id == $company_user->_user_id || $invitation->{$entity_name}->assigned_user_id == $company_user->user_id) {
            $required_permissions = $this->addSpecialUserPermissionForEntity($invitation->{$entity_name}, $required_permissions);
        } else {
            $required_permissions = $this->removeSpecialUserPermissionForEntity($invitation->{$entity_name}, $required_permissions);
        }

        if (count(array_intersect($required_permissions, $notifications->email)) >= 1) {
            array_push($notifiable_methods, 'mail');
        }

        return $notifiable_methods;
    }

    public function findUserEntityNotificationType($entity, $company_user, array $required_permissions) :array
    {
        $notifiable_methods = [];
        $notifications = $company_user->notifications;

        if ($entity->company->is_disabled ||
            ! $notifications ||
            $company_user->trashed() ||
            ! $company_user->user ||
            $company_user->user->trashed()) {
            return [];
        }

        if ($entity->user_id == $company_user->_user_id || $entity->assigned_user_id == $company_user->user_id) {
            $required_permissions = $this->addSpecialUserPermissionForEntity($entity, $required_permissions);
        } else {
            $required_permissions = $this->removeSpecialUserPermissionForEntity($entity, $required_permissions);
        }

        if (count(array_intersect($required_permissions, $notifications->email)) >= 1) {
            array_push($notifiable_methods, 'mail');
        }

        return $notifiable_methods;
    }

    private function addSpecialUserPermissionForEntity($entity, array $required_permissions) :array
    {
        array_merge($required_permissions, ['all_notifications']);

        switch ($entity) {
            case $entity instanceof Payment || $entity instanceof Client: //we pass client also as this is the proxy for Payment Failures (ie, there is no payment)
                return array_merge($required_permissions, ['all_notifications', 'all_user_notifications', 'payment_failure_user', 'payment_success_user']);
                break;
            case $entity instanceof Invoice:
                return array_merge($required_permissions, ['all_notifications', 'all_user_notifications', 'invoice_created_user', 'invoice_sent_user', 'invoice_viewed_user', 'invoice_late_user']);
                break;
            case $entity instanceof Quote:
                return array_merge($required_permissions, ['all_notifications', 'all_user_notifications', 'quote_created_user', 'quote_sent_user', 'quote_viewed_user', 'quote_approved_user', 'quote_expired_user']);
                break;
            case $entity instanceof Credit:
                return array_merge($required_permissions, ['all_notifications', 'all_user_notifications', 'credit_created_user', 'credit_sent_user', 'credit_viewed_user']);
                break;
            case $entity instanceof PurchaseOrder:
                return array_merge($required_permissions, ['all_notifications', 'all_user_notifications', 'purchase_order_created_user', 'purchase_order_sent_user', 'purchase_order_viewed_user']);
                break;
            default:
                return [];
                break;
        }
    }

    private function removeSpecialUserPermissionForEntity($entity, $required_permissions)
    {
        array_merge($required_permissions, ['all_notifications']);

        switch ($entity) {
            case $entity instanceof Payment || $entity instanceof Client: //we pass client also as this is the proxy for Payment Failures (ie, there is no payment)
                return array_diff($required_permissions, ['all_user_notifications', 'payment_failure_user', 'payment_success_user']);
                break;
            case $entity instanceof Invoice:
                return array_diff($required_permissions, ['all_user_notifications', 'invoice_created_user', 'invoice_sent_user', 'invoice_viewed_user', 'invoice_late_user']);
                break;
            case $entity instanceof Quote:
                return array_diff($required_permissions, ['all_user_notifications', 'quote_created_user', 'quote_sent_user', 'quote_viewed_user', 'quote_approved_user', 'quote_expired_user']);
                break;
            case $entity instanceof Credit:
                return array_diff($required_permissions, ['all_user_notifications', 'credit_created_user', 'credit_sent_user', 'credit_viewed_user']);
                break;
            case $entity instanceof PurchaseOrder:
                return array_diff($required_permissions, ['all_user_notifications', 'purchase_order_created_user', 'purchase_order_sent_user', 'purchase_order_viewed_user']);
                break;
            default:
                // code...
                break;
        }
    }

    public function findCompanyUserNotificationType($company_user, $required_permissions) :array
    {
        if ($company_user->company->is_disabled ||
            $company_user->trashed() ||
            ! $company_user->user ||
            $company_user->user->trashed()) {
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
        return $company_users->filter(function ($company_user) use ($required_notification, $entity) {
            return $this->checkNotificationExists($company_user, $entity, $required_notification);
        });
    }

    private function checkNotificationExists($company_user, $entity, $required_notification)
    {
        /* Always make sure we push the `all_notificaitons` into the mix */
        array_push($required_notification, 'all_notifications');

        /* Selectively add the all_user if the user is associated with the entity */
        if ($entity->user_id == $company_user->_user_id || $entity->assigned_user_id == $company_user->user_id) {
            array_push($required_notification, 'all_user_notifications');
        }

        return count(array_intersect($required_notification, $company_user->notifications->email)) >= 1;
    }
}
