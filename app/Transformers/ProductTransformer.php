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

    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="product_key", type="string", example="Item")
     * @SWG\Property(property="notes", type="string", example="Notes...")
     * @SWG\Property(property="cost", type="number", format="float", example=10.00)
     * @SWG\Property(property="qty", type="number", format="float", example=1)
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     */
    public function transform(Product $product)
    {
        return [
            'id' => $this->encodePrimaryKey($product->id),
            'product_key' => $product->product_key,
            'notes' => $product->notes,
            'cost' => (float) $product->cost,
            'qty' => (float) ($product->qty ?: 0.0),
            'tax_name1' => $product->tax_name1 ?: '',
            'tax_rate1' => (float) $product->tax_rate1,
            'tax_name2' => $product->tax_name2 ?: '',
            'tax_rate2' => (float) $product->tax_rate2,
            'updated_at' => $product->updated_at,
            'archived_at' => $product->deleted_at,
            'custom_value1' => $product->custom_value1 ?: '',
            'custom_value2' => $product->custom_value2 ?: '',
            'custom_value3' => $product->custom_value2 ?: '',
            'custom_value4' => $product->custom_value2 ?: '',
            'is_deleted' => (bool) $product->is_deleted,
        ];
    }
}
