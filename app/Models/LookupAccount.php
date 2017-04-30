<?php

namespace App\Models;

use Eloquent;

/**
 * Class ExpenseCategory.
 */
class LookupAccount extends LookupModel
{
    /**
     * @var array
     */
    protected $fillable = [
        'lookup_company_id',
        'account_key',
    ];

    public function lookupCompany()
    {
        return $this->belongsTo('App\Models\LookupCompany');
    }

}
