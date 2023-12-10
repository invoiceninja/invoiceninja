<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Jobs\User\VerifyPhone;
use App\Models\User;
use App\Utils\Ninja;

class UserObserver
{
    /**
     * Handle the app models user "created" event.
     *
     * @param User $user
     * @return void
     */
    public function created(User $user)
    {
        if (Ninja::isHosted() && isset($user->phone)) {
            VerifyPhone::dispatch($user);
        }
    }

    /**
     * Handle the app models user "updated" event.
     *
     * @param User $user
     * @return void
     */
    public function updated(User $user)
    {
        if (Ninja::isHosted() && $user->isDirty('email') && $user->company_users()->where('is_owner', true)->exists()) {
            //ensure they are owner user and update email on file.
            if (class_exists(\Modules\Admin\Jobs\Account\UpdateOwnerUser::class)) {
                \Modules\Admin\Jobs\Account\UpdateOwnerUser::dispatch($user->account->key, $user, $user->getOriginal('email'));
            }
        }
    }

    /**
     * Handle the app models user "deleted" event.
     *
     * @param User $user
     * @return void
     */
    public function deleted(User $user)
    {
        //
    }

    /**
     * Handle the app models user "restored" event.
     *
     * @param User $user
     * @return void
     */
    public function restored(User $user)
    {
        //
    }

    /**
     * Handle the app models user "force deleted" event.
     *
     * @param User $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        //
    }
}
