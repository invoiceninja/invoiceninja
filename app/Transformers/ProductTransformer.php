<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Utils\Traits\MakesHash;

class ProductTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'company',
        'user'
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

    public function transform(Product $product)
    {
        return [
            'id' => $this->encodePrimaryKey($product->id),
            'product_key' => $product->product_key ?: '',
            'notes' => $product->notes ?: '',
            'cost' => (float) $product->cost ?: '',
            'price' => (float) $product->price ?: '',
            'quantity' => (float) ($product->quantity ?: 0.0),
            'tax_name1' => $product->tax_name1 ?: '',
            'tax_rate1' => (float) $product->tax_rate1,
            'tax_name2' => $product->tax_name2 ?: '',
            'tax_rate2' => (float) $product->tax_rate2,
            'updated_at' => $product->updated_at,
            'archived_at' => $product->deleted_at,
            'custom_value1' => $product->custom_value1 ?: '',
            'custom_value2' => $product->custom_value2 ?: '',
            'custom_value3' => $product->custom_value3 ?: '',
            'custom_value4' => $product->custom_value4 ?: '',
            'is_deleted' => (bool) $product->is_deleted,
        ];
    }
}
