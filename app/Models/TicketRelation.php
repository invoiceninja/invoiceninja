<?php

namespace App\Models;

/**
 * Class TicketRelation
 * @package App\Models
 */
class TicketRelation extends EntityModel
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_RELATION;
    }

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->belongsTo('App\Models\\'.ucfirst($this->entity), 'entity_id', 'id');
    }

}
