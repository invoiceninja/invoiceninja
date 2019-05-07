<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Expense extends BaseModel
{
    use MakesHash;

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

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
