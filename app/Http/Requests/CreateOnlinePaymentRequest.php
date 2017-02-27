<?php

namespace App\Http\Requests;

use App\Models\Invitation;

class CreateOnlinePaymentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $account = $this->invitation->account;

        $paymentDriver = $account->paymentDriver($this->invitation, $this->gateway_type);
        
        return $paymentDriver->rules();
    }

    public function sanitize()
    {
        $input = $this->all();

        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.currency', 'invoice.client.account.account_gateways.gateway')
            ->where('invitation_key', '=', $this->invitation_key)
            ->firstOrFail();

        $input['invitation'] = $invitation;
        $input['gateway_type'] = session($invitation->id . 'gateway_type');

        $this->replace($input);

        return $this->all();
    }
}
