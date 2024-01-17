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

use App\Models\PurchaseOrderInvitation;
use App\Models\VendorContact;

class VendorContactObserver
{
    /**
     * Handle the vendor contact "created" event.
     *
     * @param VendorContact $vendorContact
     * @return void
     */
    public function created(VendorContact $vendorContact)
    {
        //
    }

    /**
     * Handle the vendor contact "updated" event.
     *
     * @param VendorContact $vendorContact
     * @return void
     */
    public function updated(VendorContact $vendorContact)
    {
        //
    }

    /**
     * Handle the vendor contact "deleted" event.
     *
     * @param VendorContact $vendorContact
     * @return void
     */
    public function deleted(VendorContact $vendorContact)
    {
        $vendor_contact_id = $vendorContact->id;

        $vendorContact->purchase_order_invitations()->delete();

        PurchaseOrderInvitation::withTrashed()->where('vendor_contact_id', $vendor_contact_id)->cursor()->each(function ($invite) {
            if ($invite->purchase_order()->doesnthave('invitations')) {
                $invite->purchase_order->service()->createInvitations();
            }
        });
    }

    /**
     * Handle the vendor contact "restored" event.
     *
     * @param VendorContact $vendorContact
     * @return void
     */
    public function restored(VendorContact $vendorContact)
    {
    }

    /**
     * Handle the vendor contact "force deleted" event.
     *
     * @param VendorContact $vendorContact
     * @return void
     */
    public function forceDeleted(VendorContact $vendorContact)
    {
        //
    }
}
