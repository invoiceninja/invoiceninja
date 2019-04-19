<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the app models user "created" event.
     *
     * @param  \App\Models\User $user
     * @return void
     */
    public function created(User $user)
    {
        //
    }

    /**
     * Handle the app models user "updated" event.
     *
     * @param  \App\Models\User $user
     * @return void
     */
    public function updated(User $user)
    {
        //
    }

    /**
     * Handle the app models user "deleted" event.
     *
     * @param  \App\Models\User $user
     * @return void
     */
    public function deleted(User $user)
    {
        //
    }

    /**
     * Handle the app models user "restored" event.
     *
     * @param  \App\Models\User $user
     * @return void
     */
    public function restored(User $user)
    {
        //
    }

    /**
     * Handle the app models user "force deleted" event.
     *
     * @param  \App\Models\User $user
     * @return void
     */
    public function forceDeleted(User $user)
    {
        //
    }
}
