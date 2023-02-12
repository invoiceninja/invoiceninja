<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Company;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use League\Fractal\Resource\Collection;

class ProductTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'company',
        'user',
    ];

    /**
     * @param Product $product
     *
     * @return Collection
     */
    public function includeUser(Product $product)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeItem($product->user, $transformer, User::class);
    }

    /**
     * @param Product $product
     *
     * @return Collection
     */
    public function includeCompany(Product $product)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($product->company, $transformer, Company::class);
    }

    public function includeDocuments(Product $product)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($product->documents, $transformer, Document::class);
    }

    public function transform(Product $product)
    {
        return [
            'id' => $this->encodePrimaryKey($product->id),
            'user_id' => $this->encodePrimaryKey($product->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($product->assigned_user_id),
            'product_key' => $product->product_key ?: '',
            'notes' => $product->notes ?: '',
            'cost' => (float) $product->cost ?: 0,
            'price' => (float) $product->price ?: 0,
            'quantity' => (float) $product->quantity ?: 1.0,
            'tax_name1' => $product->tax_name1 ?: '',
            'tax_rate1' => (float) $product->tax_rate1 ?: 0,
            'tax_name2' => $product->tax_name2 ?: '',
            'tax_rate2' => (float) $product->tax_rate2 ?: 0,
            'tax_name3' => $product->tax_name3 ?: '',
            'tax_rate3' => (float) $product->tax_rate3 ?: 0,
            'created_at' => (int) $product->created_at,
            'updated_at' => (int) $product->updated_at,
            'archived_at' => (int) $product->deleted_at,
            'custom_value1' => $product->custom_value1 ?: '',
            'custom_value2' => $product->custom_value2 ?: '',
            'custom_value3' => $product->custom_value3 ?: '',
            'custom_value4' => $product->custom_value4 ?: '',
            'is_deleted' => (bool) $product->is_deleted,
            'in_stock_quantity' => (int) $product->in_stock_quantity ?: 0,
            'stock_notification' => (bool) $product->stock_notification,
            'stock_notification_threshold' => (int) $product->stock_notification_threshold,
            'max_quantity' => (int) $product->max_quantity,
            'product_image' => (string) $product->product_image ?: '',
        ];
    }
}
