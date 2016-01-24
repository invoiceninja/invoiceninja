<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function getEntityType()
    {
        return ENTITY_TAX_RATE;
    }
}
