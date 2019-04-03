<?php

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