<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ExpenseCategory
 */
class ExpenseCategory extends EntityModel
{
    // Expense Categories
    use SoftDeletes;

    /**
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function expense()
    {
        return $this->belongsTo('App\Models\Expense');
    }

}