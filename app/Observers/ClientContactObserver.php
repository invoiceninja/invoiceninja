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

namespace App\Observers;

use App\Models\ClientContact;

class ClientContactObserver
{
    /**
     * Handle the client contact "created" event.
     *
     * @param  \App\Models\ClientContact  $clientContact
     * @return void
     */
    public function created(ClientContact $clientContact)
    {
        //
    }

    /**
     * Handle the client contact "updated" event.
     *
     * @param  \App\Models\ClientContact  $clientContact
     * @return void
     */
    public function updated(ClientContact $clientContact)
    {
        //
    }

    /**
     * Handle the client contact "deleted" event.
     *
     * @param  \App\Models\ClientContact  $clientContact
     * @return void
     */
    public function deleted(ClientContact $clientContact)
    {
        //
    }

    /**
     * Handle the client contact "restored" event.
     *
     * @param  \App\Models\ClientContact  $clientContact
     * @return void
     */
    public function restored(ClientContact $clientContact)
    {
        //
    }

    /**
     * Handle the client contact "force deleted" event.
     *
     * @param  \App\Models\ClientContact  $clientContact
     * @return void
     */
    public function forceDeleted(ClientContact $clientContact)
    {
        //
    }
}
