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

use App\Factory\PurchaseOrderFactory;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
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

        if(array_key_exists('vendor_id', $data)) 
            $purchase_order->vendor_id = $data['vendor_id'];

        $vendor = Vendor::where('id', $purchase_order->vendor_id)->withTrashed()->firstOrFail();    

        $state = [];

        $resource = class_basename($purchase_order); //ie Invoice

        if (! $purchase_order->id) {
            $company_defaults = $vendor->setCompanyDefaults($data, lcfirst($resource));
            $purchase_order->uses_inclusive_taxes = $vendor->getSetting('inclusive_taxes');
            $data = array_merge($company_defaults, $data);
        }

        $tmp_data = $data; //preserves the $data array

        /* We need to unset some variable as we sometimes unguard the model */
        if (isset($tmp_data['invitations'])) 
            unset($tmp_data['invitations']);
        
        if (isset($tmp_data['vendor_contacts'])) 
            unset($tmp_data['vendor_contacts']);
        
        $purchase_order->fill($tmp_data);

        $purchase_order->custom_surcharge_tax1 = $vendor->company->custom_surcharge_taxes1;
        $purchase_order->custom_surcharge_tax2 = $vendor->company->custom_surcharge_taxes2;
        $purchase_order->custom_surcharge_tax3 = $vendor->company->custom_surcharge_taxes3;
        $purchase_order->custom_surcharge_tax4 = $vendor->company->custom_surcharge_taxes4;

        if(!$purchase_order->id)
            $this->new_model = true;
        
        $purchase_order->saveQuietly();

        /* Save any documents */
        if (array_key_exists('documents', $data)) 
            $this->saveDocuments($data['documents'], $purchase_order);

        if (array_key_exists('file', $data)) 
            $this->saveDocuments($data['file'], $purchase_order);

        /* If invitations are present we need to filter existing invitations with the new ones */
        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keys which have been removed from the invitations array and soft delete each invitation */
            $purchase_order->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) {
                $invitation = PurchaseOrderInvitation::where('key', $invitation)->first();

                if ($invitation) 
                    $invitation->delete();
                
            });

            foreach ($data['invitations'] as $invitation) {

                //if no invitations are present - create one.
                if (! $this->getInvitation($invitation, $resource)) {

                    if (isset($invitation['id'])) 
                        unset($invitation['id']);

                    //make sure we are creating an invite for a contact who belongs to the client only!
                    $contact = VendorContact::find($invitation['vendor_contact_id']);

                    if ($contact && $purchase_order->client_id == $contact->client_id) {

                        $new_invitation = PurchaseOrderInvitation::withTrashed()
                                            ->where('vendor_contact_id', $contact->id)
                                            ->where('purchase_order_id', $purchase_order->id)
                                            ->first();

                        if ($new_invitation && $new_invitation->trashed()) {

                            $new_invitation->restore();

                        } else {

                            $new_invitation = PurchaseOrderFactory::create($purchase_order->company_id, $purchase_order->user_id);
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
        if ($purchase_order->invitations()->count() == 0) 
            $purchase_order->service()->createInvitations();

        /* Apply entity number */
        $purchase_order = $purchase_order->service()->applyNumber()->save();

        /* Handle attempts where the deposit is greater than the amount/balance of the invoice */
        if((int)$purchase_order->balance != 0 && $purchase_order->partial > $purchase_order->amount)
            $purchase_order->partial = min($purchase_order->amount, $purchase_order->balance);

        $purchase_order = $purchase_order->calc()->getPurchaseOrder();

        if (! $purchase_order->design_id) 
            $purchase_order->design_id = $this->decodePrimaryKey($client->getSetting('credit_design_id'));

        if(array_key_exists('invoice_id', $data) && $data['invoice_id'])
            $purchase_order->invoice_id = $data['invoice_id'];

        if($this->new_model)
            event('eloquent.created: App\Models\PurchaseOrder', $purchase_order);            
        else
            event('eloquent.updated: App\Models\PurchaseOrder', $purchase_order);


        $purchase_order->save();

        return $purchase_order->fresh();


        // $purchase_order->fill($data);
        // $purchase_order->save();

        // return $purchase_order;
    }

    public function getInvitation($invitation, $resource)
    {
        // if (is_array($invitation) && ! array_key_exists('key', $invitation))
        //     return false;
        
        // $invitation = PurchaseOrderInvitation::where('key', $invitation['key'])->first();

        return $invitation;
    }


}
