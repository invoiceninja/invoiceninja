<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Validation\Rule;

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
            'support_email_local_part' =>
            Rule::unique('account_ticket_settings')->ignore($this->user()->account->account_ticket_settings->id),
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
