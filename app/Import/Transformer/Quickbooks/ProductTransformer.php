<?php

/**
 * Invoice Ninja (https://Productninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Quickbooks;

use App\Import\Transformer\BaseTransformer;
use App\Models\Product as Model;
use App\Import\ImportException;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

/**
 * Class ProductTransformer.
 */
class ProductTransformer extends BaseTransformer
{

    protected $fillable = [
        'product_key' => 'Name',
        'notes' => 'Description',
        'cost' => 'PurchaseCost',
        'price' => 'UnitPrice',
        'quantity' => 'QtyOnHand',
        'in_stock_quantity' => 'QtyOnHand',
        'created_at' => 'CreateTime',
        'updated_at' => 'LastUpdatedTime',
    ];
    /**
     * Transforms the JSON data into a ProductModel object.
     *
     * @param array $data
     * @return ProductModel
     */
    /**
     * Transforms a Customer array into a Product model.
     *
     * @param array $data
     * @return array|bool
     */
    public function transform($data)
    {
        $transformed_data = [];
        foreach($this->fillable as $key => $field) {
            $transformed_data[$key] = method_exists($this, $method = sprintf("get%s", str_replace(".","",$field)) )? call_user_func([$this, $method],$data,$field) :  $this->getString($data, $field);
        }
        
        $transformed_data = (new Model)->fillable(array_keys($this->fillable))->fill($transformed_data);
        
        return $transformed_data->toArray() + ['company_id' => $this->company->id ] ;
    }

    public function getString($data, $field)
    {
        return Arr::get($data, $field);
    }

    public function getQtyOnHand($data, $field = null) {
        return (int) $this->getString($data, $field);
    }

    public function getPurchaseCost($data, $field = null) {
        return (float) $this->getString($data, $field);
    }


    public function getUnitPrice($data, $field = null) {
        return (float) $this->getString($data, $field);
    }

    public function getCreateTime($data, $field = null)
    {
        return $this->parseDateOrNull($data['MetaData'], 'CreateTime');
    }

    public function getLastUpdatedTime($data, $field = null)
    {
        return $this->parseDateOrNull($data['MetaData'],'LastUpdatedTime');
    }
}
