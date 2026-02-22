<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Models;

use Carbon\Carbon;
use App\Models\Product;
use App\DataMapper\ProductSync;
use App\Factory\ProductFactory;
use App\Interfaces\SyncInterface;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\ProductTransformer;

class QbProduct implements SyncInterface
{
    protected ProductTransformer $product_transformer;

    public function __construct(public QuickbooksService $service)
    {

        $this->product_transformer = new ProductTransformer($service->company);

    }
    
    /**
     * find
     *
     * Finds a product in QuickBooks by their ID.
     *
     * @param  string $id
     * @return mixed
     */
    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Item', $id);
    }
    
    /**
     * syncToNinja
     *
     * Syncs products from QuickBooks to Ninja.
     *
     * @param  array $records
     * @return void
     */
    public function syncToNinja(array $records): void
    {

        foreach ($records as $record) {
            // Double-check: Skip Category and Group items
            $item_type = data_get($record, 'Type');
            if ($item_type === 'Category' || $item_type === 'Group') {
                nlog("Skipping Category/Group item during sync: " . data_get($record, 'Name') . " (Type: {$item_type})");
                continue;
            }

            $ninja_data = $this->product_transformer->qbToNinja($record, $this->service);

            if ($product = $this->findProduct($ninja_data['id'])) {
                $product->fill($ninja_data);
                $product->save();
            }
        }

    }
    
    /**
     * syncToForeign
     *
     * Syncs products from Ninja to QuickBooks.
     *
     * @param  array $records
     * @return void
     */
    public function syncToForeign(array $records): void 
    {

        foreach ($records as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $this->createQbProduct($product);

        }
    }

        
    /**
     * findProduct
     *
     * Finds a product in Ninja by their ID.
     *
     * @param  string $key
     * @return Product
     */
    private function findProduct(string $key): ?Product
    {
        $search = Product::query()
                         ->withTrashed()
                         ->where('company_id', $this->service->company->id)
                         ->where('sync->qb_id', $key);

        if ($search->count() == 0) {

            $product = ProductFactory::create($this->service->company->id, $this->service->company->owner()->id);

            $sync = new ProductSync();
            $sync->qb_id = $key;
            $product->sync = $sync;

            return $product;

        } elseif ($search->count() == 1) {
            return $this->service->syncable('product', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
        }

        return null;

    }
    
    /**
     * sync
     *
     * Syncs a product from QuickBooks to Ninja.
     *
     * @param  string $id
     * @param  string $last_updated
     * @return void
     */
    public function sync(string $id, string $last_updated): void
    {
        $qb_record = $this->find($id);

        if ($this->service->syncable('product', \App\Enum\SyncDirection::PULL) && $ninja_record = $this->findProduct($id)) {

            if (Carbon::parse($last_updated) > Carbon::parse($ninja_record->updated_at)) {
                $ninja_data = $this->product_transformer->qbToNinja($qb_record, $this->service);

                $ninja_record->fill($ninja_data);
                $ninja_record->save();

            }

        }

    }

    /**
    * findOrCreateProduct
    *
    * Finds or creates a product in quickbooks
    *
    * @param  object $line_item
    * @return string
    */
    public function findOrCreateProduct(object $line_item): string
    {

        $product = \App\Models\Product::where('company_id', $this->service->company->id)
                                          ->where('product_key', $line_item->product_key)
                                          ->first();

        if ($product && isset($product->sync->qb_id)) {
            return $product->sync->qb_id;
        }

        $item_name = strlen($line_item->product_key ?? '') > 0 ? $line_item->product_key : 'Product ' . uniqid();

        $escaped_name = str_replace("'", "''", $item_name);
        // Only match items that can be used as line items (exclude Category/Group types)
        // QB doesn't support != operator, so we use IN with valid types
        $query = "SELECT * FROM Item WHERE Name = '{$escaped_name}' AND Active = true AND Type IN ('Service', 'NonInventory', 'Inventory') MAXRESULTS 1";

        /** @var object|array|null $existing_items */
        $existing_items = $this->service->sdk->Query($query);

        // QB SDK can return a single object or an array; normalize to array
        if (!empty($existing_items) && !is_array($existing_items)) {
            $existing_items = [$existing_items];
        }
        if (!empty($existing_items) && isset($existing_items[0])) {
            $existing_item = $existing_items[0];
            $existing_id = data_get($existing_item, 'Id') ?? data_get($existing_item, 'Id.value');
            if ($existing_id) {
                return $existing_id;
            }
        }

        return $this->createQbProduct($line_item);

    }

    /**
     * createQbProduct
     *
     * Creates a product in quickbooks
     *
     * @param  object|Product $line_item
     * @return string
     */
    private function createQbProduct(object $line_item): ?string
    {

        /** @var ?Product $product */
        $product = null;

        if ($line_item instanceof Product) {

            $product = $line_item;

            $item = new \App\DataMapper\InvoiceItem();
            $item->product_key = $line_item->product_key;
            $item->notes = $line_item->notes;
            $item->quantity = 1;
            $item->cost = $line_item->price;
            $item->line_total = $line_item->price;
            $item->type_id = $line_item->tax_id == Product::PRODUCT_TYPE_SERVICE ? '2' : '1';

            $line_item = $item;

        }

        $product_data = $this->product_transformer->qbTransform($line_item, $this->service->getIncomeAccountId());

        if (isset($product->sync->qb_id) && !empty($product->sync->qb_id)) {
            $existing_qb_product = $this->find($product->sync->qb_id);
            if ($existing_qb_product) {
                // Safety check: Don't try to update Category or Group items
                $existing_type = data_get($existing_qb_product, 'Type');
                if ($existing_type === 'Category' || $existing_type === 'Group') {
                    nlog("WARNING: Attempted to update Category/Group item in QB. Product: {$product->product_key}, QB ID: {$product->sync->qb_id}, Type: {$existing_type}. Clearing sync to allow new item creation.");
                    // Clear the QB sync so it creates a new item instead
                    $product->sync->qb_id = null;
                    $product->save();
                    // Fall through to create new item
                } else {
                    $product_data['SyncToken'] = $existing_qb_product->SyncToken ?? '0';
                    $product_data['Id'] = $product->sync->qb_id;

                    $qb_item = \QuickBooksOnline\API\Facades\Item::create($product_data);
                    $result = $this->service->sdk->Update($qb_item);

                    return $product->sync->qb_id;
                }
            }
        }

        $qb_item = \QuickBooksOnline\API\Facades\Item::create($product_data);

        $result = $this->service->sdk->Add($qb_item);

        $qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value');

        $product = \App\Models\Product::where('company_id', $this->service->company->id)
        ->where('product_key', $line_item->product_key)
        ->first();

        if ($product) {
            $sync = new ProductSync();
            $sync->qb_id = $qb_id;
            $product->sync = $sync;
            $product->save();
        }

        return $qb_id;
    }
}
