<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Models;

use App\DataMapper\ProductSync;
use App\Services\Quickbooks\QuickbooksService;

use App\Models\Product;
use App\Factory\ProductFactory;
use App\Services\Quickbooks\Transformers\ProductTransformer;


class QbProduct
{
    public function __construct(public QuickbooksService $service)
    {
    }

    public function find(int $id)
    {
        return $this->service->sdk->FindById('Item', $id);
    }


    public function syncToNinja(array $records)
    {
        
        $product_transformer = new ProductTransformer($this->service->company);

        foreach ($records as $record) {

            $ninja_data = $product_transformer->qbToNinja($record);

            if ($product = $this->findProduct($ninja_data['id'])) {
                $product->fill($ninja_data);
                $product->save();
            }
        }

    }

    private function findProduct(string $key): ?Product
    {
        $search = Product::query()
                         ->withTrashed()
                         ->where('company_id', $this->service->company->id)
                         ->where('sync->qb_id', $key);
             
        if($search->count() == 0) {
            
            $product = ProductFactory::create($this->service->company->id, $this->service->company->owner()->id);

            $sync = new ProductSync();
            $sync->qb_id = $key;
            $product->sync = $sync;
            
            return $product;

        } elseif($search->count() == 1) {
            return $this->service->settings->product->update_record ? $search->first() : null;
        }

        return null;


    }
}
