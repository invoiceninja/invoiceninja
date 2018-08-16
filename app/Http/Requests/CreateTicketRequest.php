<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Ticket;

class CreateTicketRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    protected $autoload = [
        ENTITY_CLIENT
    ];

    public function authorize()
    {
        return $this->user()->can('create', ENTITY_TICKET);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */

    public function rules()
    {
        $rules = [];
        $rules['subject'] = 'required';
        $rules['description'] = 'required';

        if(request()->input('is_internal'))
            $rules['agent_id'] = 'required';
        else
            $rules['client_public_id']= 'required';

        
        return $rules;
    }

    public function sanitize()
    {

        $data = $this->all();

        if($data['client_public_id'] > 0 && !isset($data['contact_key'])){
            $client = Client::scope($data['client_public_id'])->first();
            $contact = $client->getPrimaryContact();
            $data['contact_key'] = $contact->contact_key;
        }

        if($data['parent_ticket_id'] > 0)
            $data['parent_ticket_id'] = Ticket::getPrivateId($data['parent_ticket_id']);


        $this->replace($data);

        return $this->all();
    }
}
