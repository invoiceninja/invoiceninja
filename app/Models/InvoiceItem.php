<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',    
    ];

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

}
