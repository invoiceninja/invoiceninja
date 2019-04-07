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
				//account_id
				//user_id
				'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
				'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
				'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
				'product_key' => $this->getString($data, 'product_key'),
				'notes' => $this->getString($data, 'notes'),
				'cost' => $this->getFloat($data, 'cost'),
				'qty' => $this->getFloat($data, 'item_quantity') ?: 1,
                'public_id' => $this->getProduct($data, 'product_key', 'public_id'),
                //'is_deleted' => $clientId ? false : true,
                'custom_value1' => $this->getString($data, 'custom_value1'),
                'custom_value2' => $this->getString($data, 'custom_value2'),
				'tax_name1' => $this->getTaxName($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_name1', ''),
                'tax_rate1' => $this->getTaxRate($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_rate1', 0),
				'tax_name2' => $this->getTaxName($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_name2', ''),
                'tax_rate2' => $this->getTaxRate($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_rate2', 0),
            ];
        });
    }
}
