<?php namespace App\Http\Requests;

class VendorRequest extends EntityRequest {

    protected $entityType = ENTITY_VENDOR;

    public function entity()
    {
        $vendor = parent::entity();
        
        // eager load the contacts
        if ($vendor && ! count($vendor->vendorcontacts)) {
            $vendor->load('vendorcontacts');
        }
         
        return $vendor;
    }

}