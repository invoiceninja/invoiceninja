<?php

namespace App\Http\Requests;


class TicketMergeRequest extends EntityRequest
{
    protected $entityType = ENTITY_TICKET;


    public function entity()
    {
        return parent::entity();
    }

    public function rules()
    {
        return [
            'updated_ticket_id' => 'required',
        ];
    }

    public function authorize()
    {
        return true;
    }
}
