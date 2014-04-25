<?php

class Product extends EntityModel
{	
	public static function findProductByKey($key)
	{
		return Product::scope()->where('product_key','=',$key)->first();
	}
}