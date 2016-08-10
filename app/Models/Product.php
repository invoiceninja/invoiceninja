<?php namespace App\Models;

use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Product
 */
class Product extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\ProductPresenter';

    /**
     * @var array
     */
    protected $fillable = [
        'product_key',
        'notes',
        'cost',
        'qty',
        'default_tax_rate_id',
    ];

    /**
     * @return array
     */
    public static function getImportColumns()
    {
        return [
            'product_key',
            'notes',
            'cost',
        ];
    }

    /**
     * @return array
     */
    public static function getImportMap()
    {
        return [
            'product|item' => 'product_key',
            'notes|description|details' => 'notes',
            'cost|amount|price' => 'cost',
        ];
    }

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PRODUCT;
    }

    /**
     * @param $key
     * @return mixed
     */
    public static function findProductByKey($key)
    {
        return Product::scope()->where('product_key', '=', $key)->first();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function default_tax_rate()
    {
        return $this->belongsTo('App\Models\TaxRate');
    }
}
