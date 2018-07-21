<?php

namespace App\Http\Requests;

use App\Models\Ticket;

class SaveTicketSettings extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
        ];
    }

    public function sanitize()
    {
        $input = $this->all();

        //ensure we
        $maxTicketNumber = Ticket::scope()->withTrashed()->max('ticket_number');

        if($input['ticket_number_start'] <= $maxTicketNumber){

            $input['ticket_number_start'] = $maxTicketNumber+1;

            $this->replace($input);

            return $this->all();
        }

        return $input;
    }
}
