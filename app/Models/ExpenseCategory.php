<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;


class ExpenseCategory extends EntityModel
{
    // Expense Categories
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function expense()
    {
        return $this->belongsTo('App\Models\Expense');
    }

}