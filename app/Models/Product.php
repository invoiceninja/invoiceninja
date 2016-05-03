<?php namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'product_key',
        'notes',
        'cost',
        'qty',
        'default_tax_rate_id',
    ];

    public function getEntityType()
    {
        return ENTITY_PRODUCT;
    }

    public static function findProductByKey($key)
    {
        return Product::scope()->where('product_key', '=', $key)->first();
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function default_tax_rate()
    {
        return $this->belongsTo('App\Models\TaxRate');
    }
}
