<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
