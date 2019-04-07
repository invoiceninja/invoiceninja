<?php

namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

// vendor
/**
 * Class VendorTransformer.
 */
class VendorTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if (isset($data->name) && $this->hasVendor($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
				'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
				'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
				'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
				//user_id
				//account_id
				//currency_id
			
			
			
                'name' => $this->getString($data, 'name'),
				'address1' => $this->getString($data, 'address1'),
				'address2' => $this->getString($data, 'address2'),
				'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'state'),
                'postal_code' => $this->getString($data, 'postal_code'),
				'country_id' => isset($data->country) ? $this->getCountryId($data->country) : null,
                'work_phone' => $this->getString($data, 'work_phone'),
                'private_notes' => $this->getString($data, 'private_notes'),
				'website' => $this->getString($data, 'website'),
				//'is_deleted' => $clientId ? false : true,
				//public_id
				'vat_number' => $this->getString($data, 'vat_number'),
                'id_number' => $this->getString($data, 'id_number'),
				//'transaction_name => $this->getString($data, 'transaction_name')
				'custom_value1' => $this->getString($data, 'custom1'),
                'custom_value2' => $this->getString($data, 'custom2'),
                'vendor_contacts' => [
                    [
						//account_id
						//user_id
						'vendor_id' => isset($data->vendor) ? $this->getCountryId($data->vendor) : null,
						'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
						'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
						'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
						//is_primary
                        'first_name' => $this->getString($data, 'contact_first_name'),
                        'last_name' => $this->getString($data, 'contact_last_name'),
                        'email' => $this->getString($data, 'contact_email'),
                        'phone' => $this->getString($data, 'contact_phone'),
						//public_id
                    ],
                ],
            ];
        });
    }
}
