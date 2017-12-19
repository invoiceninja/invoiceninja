<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PaymentTerm.
 */
class TaskStatus extends EntityModel
{
    use SoftDeletes;

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'sort_order',
    ];


    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TASK_STATUS;
    }
}
