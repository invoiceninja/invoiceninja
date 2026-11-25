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
use App\Services\Quickbooks\QuickbooksFaultParser;
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
        return $this->service->sdk()->findById('Item', $id);
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

            if ($product = $this->findProduct($ninja_data['id'], $ninja_data['product_key'])) {
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
            if ($product instanceof Product) {
                $this->createQbProduct($product);
            }
        }
    }


    /**
     * findProduct
     *
     * Finds a product in Ninja by their ID.
     *
     * @param  string $key
     * @param  string $product_key
     * @return Product
     */
    private function findProduct(string $key, ?string $product_key = null): Product
    {
        $search = Product::query()
                         ->withTrashed()
                         ->where('company_id', $this->service->company->id)
                         ->where('sync->qb_id', $key);

        if ($search->count() >= 1) {
            return $search->first();
        }

        if ($product_key) {
            $product_match = Product::query()
                ->withTrashed()
                ->where('company_id', $this->service->company->id)
                ->where('product_key', $product_key)
                ->first();

            if ($product_match) {
                $sync = $product_match->sync ? clone $product_match->sync : new ProductSync();
                $sync->qb_id = $key;
                $product_match->sync = $sync;
                $product_match->saveQuietly();

                return $product_match;
            }
        }

        $product = ProductFactory::create($this->service->company->id, $this->service->company->owner()->id);

        $sync = new ProductSync();
        $sync->qb_id = $key;
        $product->sync = $sync;

        return $product;

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

        if ($this->service->syncable('product', \App\Enum\SyncDirection::PULL)) {

            $ninja_record = $this->findProduct($id);

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
        $product = $line_item instanceof Product
            ? $line_item
            : Product::where('company_id', $this->service->company->id)
                ->where('product_key', $line_item->product_key)
                ->first();

        if (!empty($product->sync->qb_id)) {
            return $product->sync->qb_id;
        }

        $qb_id = $this->findExistingProductId($line_item->product_key ?? '');

        if ($qb_id === null) {
            return $this->createQbProduct($line_item);
        }

        if ($product) {
            $this->linkProduct($product, $qb_id);
        }

        return $qb_id;
    }


    private function findExistingProductId(string $item_name): ?string
    {
        if (trim($item_name) === '') {
            return null;
        }

        $escaped_name = str_replace("'", "\\'", $item_name);
        $query = "SELECT * FROM Item WHERE Name = '{$escaped_name}' AND Active = true AND Type IN ('Service', 'NonInventory', 'Inventory') MAXRESULTS 2";
        $items = $this->service->sdk()->query($query);

        if (empty($items)) {
            return null;
        }

        $items = is_array($items) && array_is_list($items) ? $items : [$items];

        if (count($items) !== 1) {
            throw new \RuntimeException("Multiple QuickBooks items match '{$item_name}'. Link the correct item before retrying.");
        }

        $qb_id = data_get($items[0], 'Id.value') ?? data_get($items[0], 'Id');

        if (empty($qb_id) || !is_scalar($qb_id)) {
            throw new \RuntimeException("QuickBooks returned an invalid item ID for '{$item_name}'.");
        }

        return (string) $qb_id;
    }

    private function linkProduct(Product $product, string $qb_id): void
    {
        $sync = $product->sync ? clone $product->sync : new ProductSync();
        $sync->qb_id = $qb_id;
        $sync->qb_status_message = '';
        $product->sync = $sync;
        $product->saveQuietly();
    }

    /**
     * createQbProduct
     *
     * Creates a product in quickbooks
     *
     * @param  mixed $line_item
     * @return string
     */
    private function createQbProduct(mixed $line_item): string
    {
        $product = $line_item instanceof Product ? $line_item : null;

        if ($product) {
            if (empty($product->sync->qb_id)) {
                $qb_id = $this->findExistingProductId($product->product_key);

                if ($qb_id !== null) {
                    $this->linkProduct($product, $qb_id);
                }
            }

            $item = new \App\DataMapper\InvoiceItem();
            $item->product_key = $product->product_key;
            $item->notes = $product->notes;
            $item->quantity = 1;
            $item->cost = $product->price;
            $item->product_cost = $product->cost ?? 0;
            $item->line_total = $product->price;
            $item->type_id = $product->tax_id == Product::PRODUCT_TYPE_SERVICE ? '2' : '1';
            $item->tax_id = (string) $product->tax_id;
            $item->income_account_id = $product->income_account_id ? (string) $product->income_account_id : '';
            $line_item = $item;
        }

        $product_data = $this->product_transformer->qbTransform($line_item, $this->service->getIncomeAccountId());

        if (!empty($product->sync->qb_id)) {
            $existing_item = $this->find($product->sync->qb_id);

            if (!$existing_item) {
                $exception = new \RuntimeException("Mapped QuickBooks item {$product->sync->qb_id} could not be loaded. The mapping has been preserved.");
                $this->markPushFailure($product, $exception, 'resolving the product');
                throw $exception;
            }

            $existing_type = data_get($existing_item, 'Type.value') ?? data_get($existing_item, 'Type');

            if (!filter_var(data_get($existing_item, 'Active', true), FILTER_VALIDATE_BOOLEAN)
                || !in_array($existing_type, ['Service', 'NonInventory', 'Inventory'], true)) {
                $exception = new \RuntimeException("QuickBooks item {$product->sync->qb_id} is inactive or has an unsupported type. The mapping has been preserved.");
                $this->markPushFailure($product, $exception, 'resolving the product');
                throw $exception;
            }

            $product_data['SyncToken'] = data_get($existing_item, 'SyncToken', '0');
            $product_data['Id'] = $product->sync->qb_id;
            $qb_item = \QuickBooksOnline\API\Facades\Item::create($product_data);

            try {
                $this->service->sdk()->update($qb_item);
                $this->clearPushFailure($product);
            } catch (\Throwable $exception) {
                $this->markPushFailure($product, $exception, 'updating the product');
                throw $exception;
            }

            return $product->sync->qb_id;
        }

        $qb_item = \QuickBooksOnline\API\Facades\Item::create($product_data);

        try {
            $result = $this->service->sdk()->add($qb_item);
        } catch (\Throwable $exception) {
            if ($product) {
                $this->markPushFailure($product, $exception, 'creating the product');
            }

            throw $exception;
        }

        $qb_id = data_get($result, 'Id.value') ?? data_get($result, 'Id');

        if (empty($qb_id) || !is_scalar($qb_id)) {
            $exception = new \RuntimeException('QuickBooks did not return a valid product ID.');

            if ($product) {
                $this->markPushFailure($product, $exception, 'creating the product');
            }

            throw $exception;
        }

        $product ??= Product::where('company_id', $this->service->company->id)
            ->where('product_key', $line_item->product_key)
            ->first();

        if ($product) {
            $this->linkProduct($product, (string) $qb_id);
        }

        return (string) $qb_id;
    }

    private function markPushFailure(Product $product, \Throwable $e, string $operation): void
    {
        $sync = $product->sync ?? new ProductSync();
        $sync->qb_status_message = (new QuickbooksFaultParser())->statusMessage($e, $operation);
        $product->sync = $sync;
        $product->saveQuietly();
    }

    private function clearPushFailure(Product $product): void
    {
        $sync = $product->sync ?? new ProductSync();
        $sync->qb_status_message = '';
        $product->sync = $sync;
        $product->saveQuietly();
    }
}
