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
                'product_key' => $this->getString($data, 'product_key'),
                'notes' => $this->getString($data, 'notes'),
                'cost' => $this->getFloat($data, 'cost'),
            ];
        });
    }
}
