<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 *
 */
class ProductRepository extends BaseRepository
{
    public function getClassName()
    {
        return Product::class;
    }

    /**
     * @param array $data
     * @param Product $product
     * @return Product|null
     */
    public function save(array $data, Product $product) : ?Product
    {
        $product->fill($data);
        $product->save();

        return $product;
    }
}
