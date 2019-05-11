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
    
	public function save(Request $request, Product $product) : ?Product
	{
        $product->fill($request->input());
        $product->save();

        return $product;
	}

}