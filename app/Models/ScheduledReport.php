<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Scheduled Report
 */
class ScheduledReport extends EntityModel
{
    use SoftDeletes;

    /**
     * @var array
     */
    protected $fillable = [
        'frequency',
        'config',
    ];

}
