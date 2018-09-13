<?php

namespace App\Http\Requests;

class TicketRequest extends EntityRequest
{
    protected $entityType = ENTITY_TICKET;


    public function entity()
    {

        $ticket = parent::entity();

        // eager load the documents
        if ($ticket && method_exists($ticket, 'documents') && ! $ticket->relationLoaded('documents'))
            $ticket->load('documents', 'relations');

        return $ticket;

    }


    public function authorize()
    {
        if(request()->is('tickets/create*') && $this->user()->can('createEntity', ENTITY_TICKET))
            return true;
        elseif (request()->is('tickets/*/edit') && $this->user()->can('view', $this->entity()))
            return true;
        else
            return false;
    }

}
