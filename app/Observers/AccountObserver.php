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

use App\Models\Account;

class AccountObserver
{
    /**
     * Handle the account "created" event.
     *
     * @param Account $account
     * @return void
     */
    public function created(Account $account)
    {
        //
    }

    /**
     * Handle the account "updated" event.
     *
     * @param Account $account
     * @return void
     */
    public function updated(Account $account)
    {
        //
    }

    /**
     * Handle the account "deleted" event.
     *
     * @param Account $account
     * @return void
     */
    public function deleted(Account $account)
    {
        //
    }

    /**
     * Handle the account "restored" event.
     *
     * @param Account $account
     * @return void
     */
    public function restored(Account $account)
    {
        //
    }

    /**
     * Handle the account "force deleted" event.
     *
     * @param Account $account
     * @return void
     */
    public function forceDeleted(Account $account)
    {
        //
    }
}
