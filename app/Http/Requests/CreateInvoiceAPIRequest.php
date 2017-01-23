<?php namespace App\Http\Requests;

use App\Models\Client;

class CreateInvoiceAPIRequest extends InvoiceRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_INVOICE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'email' => 'required_without:client_id',
            'client_id' => 'required_without:email',
            'invoice_items' => 'valid_invoice_items',
            'invoice_number' => 'unique:invoices,invoice_number,,id,account_id,' . $this->user()->account_id,
            'discount' => 'positive',
            //'invoice_date' => 'date',
            //'due_date' => 'date',
            //'start_date' => 'date',
            //'end_date' => 'date',
        ];

        if ($this->user()->account->client_number_counter) {
            $clientId = Client::getPrivateId(request()->input('client')['public_id']);
            $rules['client.id_number'] = 'unique:clients,id_number,'.$clientId.',id,account_id,' . $this->user()->account_id;
        }

        return $rules;
    }
}
