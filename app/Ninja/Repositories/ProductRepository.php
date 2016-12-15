<?php namespace App\Ninja\Repositories;

use DB;
use App\Models\Product;

class ProductRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Product';
    }

    public function all()
    {
        return Product::scope()
                ->withTrashed()
                ->get();
    }

    public function find($accountId, $filter = null)
    {
        $query = DB::table('products')
                ->leftJoin('tax_rates', function($join) {
                    $join->on('tax_rates.id', '=', 'products.default_tax_rate_id')
                         ->whereNull('tax_rates.deleted_at');
                })
                ->where('products.account_id', '=', $accountId)
                ->select(
                    'products.public_id',
                    'products.product_key',
                    'products.notes',
                    'products.cost',
                    'tax_rates.name as tax_name',
                    'tax_rates.rate as tax_rate',
                    'products.deleted_at',
                    'products.is_deleted'
                );

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('products.product_key', 'like', '%'.$filter.'%')
                      ->orWhere('products.notes', 'like', '%'.$filter.'%');
            });
        }

        $this->applyFilters($query, ENTITY_PRODUCT);

        return $query;
    }

    public function save($data, $product = null)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($product) {
            // do nothing
        } elseif ($publicId) {
            $product = Product::scope($publicId)->firstOrFail();
            \Log::warning('Entity not set in product repo save');
        } else {
            $product = Product::createNew();
        }

        $product->fill($data);
        $product->save();

        return $product;
    }

    public function findPhonetically($productName)
    {
        $productNameMeta = metaphone($productName);

        $map = [];
        $max = SIMILAR_MIN_THRESHOLD;
        $productId = 0;

        $products = Product::scope()
                        ->with('default_tax_rate')
                        ->get();

        foreach ($products as $product) {
            if ( ! $product->product_key) {
                continue;
            }

            $map[$product->id] = $product;
            $similar = similar_text($productNameMeta, metaphone($product->product_key), $percent);

            if ($percent > $max) {
                $productId = $product->id;
                $max = $percent;
            }
        }

        return ($productId && isset($map[$productId])) ? $map[$productId] : null;
    }


}
