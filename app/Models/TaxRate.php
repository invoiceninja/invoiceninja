<?php namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name',
        'rate'
    ];

    public function getEntityType()
    {
        return ENTITY_TAX_RATE;
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }
}
