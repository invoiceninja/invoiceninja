<?php

class Product extends Eloquent
{
	protected $softDelete = true;

	public function scopeScope($query)
	{
		return $query->whereAccountId(Auth::user()->account_id);
	}

	public static function findProductByKey($key)
	{
		return Product::scope()->where('key','=',$key)->first();
	}

	public static function getProductKeys($products)
	{
		$products = array_pluck($products, 'key');
		$products = array_combine($products, $products);		

		return $products;
	}
}