<?php namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;
// vendor
class VendorTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ($this->hasVendor($data->vendor_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $data->vendor_name,
            ];
        });
    }
}
