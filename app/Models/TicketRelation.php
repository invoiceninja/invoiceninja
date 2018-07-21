<?php

namespace App\Models;

class TicketRelation extends EntityModel
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_RELATION;
    }
}
