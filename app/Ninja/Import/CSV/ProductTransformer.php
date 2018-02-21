<?php

namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class ProductTransformer.
 */
class ProductTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if (empty($data->product_key)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'public_id' => $this->getProduct($data, 'product_key', 'public_id'),
                'product_key' => $this->getString($data, 'product_key'),
                'notes' => $this->getString($data, 'notes'),
                'cost' => $this->getFloat($data, 'cost'),
                'custom_value1' => $this->getString($data, 'custom_value1'),
                'custom_value2' => $this->getString($data, 'custom_value2'),
            ];
        });
    }
}
