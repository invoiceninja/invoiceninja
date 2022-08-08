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

namespace App\Repositories;

use App\Factory\PurchaseOrderInvitationFactory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;

class PurchaseOrderRepository extends BaseRepository
{
    use MakesHash;

    public function __construct()
    {
    }

    public function save(array $data, PurchaseOrder $purchase_order) : ?PurchaseOrder
    {
        $purchase_order->fill($data);

        $purchase_order->save();

        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keys which have been removed from the invitations array and soft delete each invitation */
            $purchase_order->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) {
                $invitation = PurchaseOrderInvitation::where('key', $invitation)->first();

                if ($invitation) {
                    $invitation->delete();
                }
            });

            foreach ($data['invitations'] as $invitation) {

                //if no invitations are present - create one.
                if (! $this->getInvitation($invitation)) {
                    if (isset($invitation['id'])) {
                        unset($invitation['id']);
                    }

                    //make sure we are creating an invite for a contact who belongs to the client only!
                    $contact = VendorContact::find($invitation['vendor_contact_id']);

                    if ($contact && $purchase_order->vendor_id == $contact->vendor_id) {
                        $new_invitation = PurchaseOrderInvitation::withTrashed()
                                            ->where('vendor_contact_id', $contact->id)
                                            ->where('purchase_order_id', $purchase_order->id)
                                            ->first();

                        if ($new_invitation && $new_invitation->trashed()) {
                            $new_invitation->restore();
                        } else {
                            $new_invitation = PurchaseOrderInvitationFactory::create($purchase_order->company_id, $purchase_order->user_id);
                            $new_invitation->purchase_order_id = $purchase_order->id;
                            $new_invitation->vendor_contact_id = $contact->id;
                            $new_invitation->key = $this->createDbHash($purchase_order->company->db);
                            $new_invitation->save();
                        }
                    }
                }
            }
        }

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if ($purchase_order->invitations()->count() == 0) {
            $purchase_order->service()->createInvitations();
        }

        /* Recalculate invoice amounts */
        $purchase_order = $purchase_order->calc()->getPurchaseOrder();

        return $purchase_order;
    }

    public function getInvitationByKey($key) :?PurchaseOrderInvitation
    {
        return PurchaseOrderInvitation::where('key', $key)->first();
    }

    public function getInvitation($invitation, $resource = null)
    {
        if (is_array($invitation) && ! array_key_exists('key', $invitation)) {
            return false;
        }

        $invitation = PurchaseOrderInvitation::where('key', $invitation['key'])->first();

        return $invitation;
    }
}
