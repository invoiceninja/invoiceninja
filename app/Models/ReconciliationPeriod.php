<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationPeriod extends Model
{
    protected $table = 'reconciliation_periods';

    protected $fillable = [
        'company_id',
        'from_date',
        'to_date',
        'locked_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
        'locked_at' => 'datetime',
    ];

    public static function isLocked(int $companyId, $date): bool
    {
        return self::where('company_id', $companyId)
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->whereNotNull('locked_at')
            ->exists();
    }
}
