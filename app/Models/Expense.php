<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends BaseModel
{
    protected $guarded = [
    	'id',
    ];

    protected $appends = ['expense_id'];

    public function getRouteKeyName()
    {
        return 'expense_id';
    }

    public function getExpenseIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }
}
