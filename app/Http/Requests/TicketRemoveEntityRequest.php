<?php

namespace App\Http\Requests;

class TicketRemoveEntityRequest extends EntityRequest
{
    protected $entityType = ENTITY_TICKET;

    public function authorize()
    {
        return $this->user()->can('edit', Ticket::class);
    }

}