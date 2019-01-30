<?php

namespace App\Models;

use Eloquent;


class TicketCategory extends Eloquent
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_CATEGORY;
    }
}
