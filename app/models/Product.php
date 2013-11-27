<?php

class Product extends Eloquent
{
	protected $softDelete = true;

	public static function getProducts()
	{
		return Product::where('account_id','=',Auth::user()->account_id);
	}

	public static function findProduct($key)
	{
		return Product::getProducts()->where('product_key','=',$key)->first();
	}

	public static function getProductKeys($products)
	{
		$products = array_pluck($products, 'product_key');
		$products = array_combine($products, $products);		

		return $products;
	}
}