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

use App\Import\Transformer\Quickbooks\CommonTrait;
use App\Import\Transformer\BaseTransformer;
use App\Models\Product as Model;
use App\Import\ImportException;

/**
 * Class ProductTransformer.
 */
class ProductTransformer extends BaseTransformer
{
    use CommonTrait;

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


    public function __construct($company)
    {
        parent::__construct($company);

        $this->model = new Model();
    }

    public function getQtyOnHand($data, $field = null)
    {
        return (int) $this->getString($data, $field);
    }

    public function getPurchaseCost($data, $field = null)
    {
        return (float) $this->getString($data, $field);
    }


    public function getUnitPrice($data, $field = null)
    {
        return (float) $this->getString($data, $field);
    }
}
