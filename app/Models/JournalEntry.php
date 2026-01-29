<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ReconciliationPeriod;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'company_id',
        'chart_of_account_id',
        'source_type',
        'source_id',
        'debit',
        'credit',
        'description',
        'entry_date',
    ];

    protected $casts = [
        'debit'  => 'decimal:6',
        'credit' => 'decimal:6',
        'entry_date' => 'date',
    ];

    /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    protected static function booted()
    {
        static::creating(function ($entry) {
            if (ReconciliationPeriod::isLocked($entry->company_id, $entry->entry_date)) {
                abort(403, 'Accounting period is locked');
            }
        });
    }
}
