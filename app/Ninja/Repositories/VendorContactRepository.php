<?php

namespace App\Ninja\Repositories;

use App\Models\VendorContact;

/**
 * Class VendorContactRepository
 */
class VendorContactRepository extends BaseRepository
{
    /**
     * @param array $data
     * @return mixed
     */
    public function save(array $data)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (!$publicId || $publicId == '-1') {
            /** @var VendorContact $contact */
            $contact = VendorContact::createNew();
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