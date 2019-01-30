<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateTicketRequest extends EntityRequest
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
        return $this->user()->can('create', Ticket::class);
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

        if(isset($data['client_public_id']) && $data['client_public_id'] > 0 && !isset($data['contact_key'])){
            $client = Client::scope($data['client_public_id'])->first();
            $contact = $client->getPrimaryContact();
            $data['contact_key'] = $contact->contact_key;
        }

        if($data['parent_ticket_id'] > 0)
            $data['parent_ticket_id'] = Ticket::getPrivateId($data['parent_ticket_id']);

        //if(isset($data['agent_id']) && $data['agent_id'] == 0)
        //    $data['agent_id'] = Auth::user()->id;

        $this->replace($data);

        return $this->all();
    }
}
