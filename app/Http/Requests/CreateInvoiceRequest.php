<?php namespace app\Http\Requests;

use Auth;
use App\Http\Requests\Request;
use Illuminate\Validation\Factory;
use App\Models\Invoice;

class CreateInvoiceRequest extends Request
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
        $rules = [
            'email' => 'required_without:client_id',
            'client_id' => 'required_without:email',
            'invoice_items' => 'valid_invoice_items',
            'invoice_number' => 'unique:invoices,invoice_number,,id,account_id,'.Auth::user()->account_id,
            'discount' => 'positive',
        ];

        return $rules;
    }
}
