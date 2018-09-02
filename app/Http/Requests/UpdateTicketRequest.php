<?php

namespace App\Http\Requests;

use App\Models\Client;

class UpdateTicketRequest extends TicketRequest
{
    protected $autoload = [
        ENTITY_CLIENT
    ];

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

        $data = $this->all();

        $rules =  [
            'client_public_id' => 'min:1|numeric',
        ];

        if($data['is_internal'] && $data['is_internal'] == false)
            $rules['client_public_id'] = 'min:1|numeric|required';

        return $rules;
    }

    public function sanitize()
    {

        $data = $this->all();

        if(isset($data['client_public_id']) && $data['client_public_id'] > 0 && !isset($data['contact_key'])){
            $client = Client::scope($data['client_public_id'])->first();
            $contact = $client->getPrimaryContact();
            $data['contact_key'] = $contact->contact_key;
        }

        if(isset($data['parent_ticket_id']) && $data['parent_ticket_id'] > 0)
            $data['parent_ticket_id'] = Ticket::getPrivateId($data['parent_ticket_id']);

        if(isset($data['agent_id']) && $data['agent_id'] > 0)
            $data['user_id'] = $data['agent_id'];

        $this->replace($data);

        return $this->all();
    }
}
