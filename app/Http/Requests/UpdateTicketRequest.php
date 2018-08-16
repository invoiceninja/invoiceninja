<?php

namespace App\Http\Requests;

use App\Models\Client;

class UpdateTicketRequest extends TicketRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->entity() && $this->user()->can('edit', $this->entity());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'client_id' => 'min:1|numeric',
        ];
    }


    public function sanitize()
    {

        $data = $this->all();

        if(isset($data['client_id']) && $data['client_id'] > 0) {
            $client = Client::scope($data['client_id'])->first();
            $data['client_id'] = $client->id;

            if(!isset($data['contact_key']) && $client){
                $contact = $client->getPrimaryContact();
                $data['contact_key'] = $contact->contact_key;
            }

        }

        if(isset($data['parent_ticket_id']))
            $data['parent_ticket_id'] = Ticket::getPrivateId($data['parent_ticket_id']);

        $this->replace($data);

        return $this->all();
    }
}
