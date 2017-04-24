<?php

namespace App\Ninja\Repositories;

use App\Models\Vendor;
use App\Models\VendorContact;

// vendor
class VendorContactRepository extends BaseRepository
{
    public function save($data)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (! $publicId || $publicId == '-1') {
            $contact = VendorContact::createNew();
            //$contact->send_invoice = true;
            $contact->vendor_id = $data['vendor_id'];
            $contact->is_primary = VendorContact::scope()->where('vendor_id', '=', $contact->vendor_id)->count() == 0;
        } else {
            $contact = VendorContact::scope($publicId)->firstOrFail();
        }

        $contact->fill($data);
        $contact->save();

        return $contact;
    }
}
