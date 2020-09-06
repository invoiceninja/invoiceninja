<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Company;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use App\Transformers\DocumentTransformer;
use App\Utils\Traits\MakesHash;

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
        'documents',
    ];

    /**
     * @param Product $product
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeUser(Product $product)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeItem($product->user, $transformer, User::class);
    }

    /**
     * @param Product $product
     *
     * @return \League\Fractal\Resource\Collection
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
        ];
    }
}
