<?php

class Product extends EntityModel
{	
	public static function findProductByKey($key)
	{
		return Product::scope()->where('product_key','=',$key)->first();
	}

	public static function getProductKeys($products)
	{
		$products = array_pluck($products, 'product_key');
		$products = array_combine($products, $products);		

		return $products;
	}
}