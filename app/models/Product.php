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
		return Product::getProducts()->where('key','=',$key)->first();
	}

	public static function getProductKeys($products)
	{
		$products = array_pluck($products, 'key');
		$products = array_combine($products, $products);		

		return $products;
	}
}