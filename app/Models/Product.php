<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public static function findProductByKey($key)
    {
        return Product::scope()->where('product_key', '=', $key)->first();
    }
}
