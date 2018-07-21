<?php

namespace App\Http\Requests;

class TicketRequest extends EntityRequest
{
    protected $entityType = ENTITY_TICKET;

    public function entity()
    {
        $ticket = parent::entity();

        // eager load the documents
        if ($ticket && method_exists($ticket, 'documents') && ! $ticket->relationLoaded('documents')) {
            $ticket->load('documents');
        }

        return $ticket;
    }
}
