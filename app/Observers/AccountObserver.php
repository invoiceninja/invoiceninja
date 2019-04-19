<?php

namespace App\Observers;

use App\Models\Account;

class AccountObserver
{
    /**
     * Handle the account "created" event.
     *
     * @param  \App\App\Models\Account  $account
     * @return void
     */
    public function created(Account $account)
    {
        //
    }

    /**
     * Handle the account "updated" event.
     *
     * @param  \App\App\Models\Account  $account
     * @return void
     */
    public function updated(Account $account)
    {
        //
    }

    /**
     * Handle the account "deleted" event.
     *
     * @param  \App\App\Models\Account  $account
     * @return void
     */
    public function deleted(Account $account)
    {
        //
    }

    /**
     * Handle the account "restored" event.
     *
     * @param  \App\App\Models\Account  $account
     * @return void
     */
    public function restored(Account $account)
    {
        //
    }

    /**
     * Handle the account "force deleted" event.
     *
     * @param  \App\App\Models\Account  $account
     * @return void
     */
    public function forceDeleted(Account $account)
    {
        //
    }
}
