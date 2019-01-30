<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class TicketTemplate extends EntityModel
{

    use SoftDeletes;

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @return mixed
     */

    public function getEntityType()
    {
        return ENTITY_TICKET_TEMPLATE;
    }

    /**
     * @return mixed
     */

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return mixed
     */

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
