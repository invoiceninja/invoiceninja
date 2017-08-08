<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TaxRate.
 */
class TaxRate extends EntityModel
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'rate',
        'is_inclusive',
    ];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TAX_RATE;
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return bool|string
     */
    public function __toString()
    {
        return sprintf('%s: %s%%', $this->name, $this->rate);
    }
}
