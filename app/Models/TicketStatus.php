<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class TicketStatus extends EntityModel
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
        'trigger_column',
        'trigger_threshold',
        'color',
        'sort_order',
        'category_id',
    ];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_STATUS;
    }

    public function category()
    {
        return $this->belongsTo('App\Models\TicketCategory');
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/tickets/status/{$this->public_id}";
    }
}
