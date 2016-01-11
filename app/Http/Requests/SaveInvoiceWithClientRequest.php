<?php namespace app\Http\Requests;

use Auth;
use App\Http\Requests\Request;
use Illuminate\Validation\Factory;
use App\Models\Invoice;

class SaveInvoiceWithClientRequest extends Request
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
        $publicId = Request::get('public_id');
        $invoiceId = $publicId ? Invoice::getPrivateId($publicId) : '';
        
        $rules = [
            'client.contacts' => 'valid_contacts',
            'invoice_items' => 'valid_invoice_items',
            'invoice_number' => 'required|unique:invoices,invoice_number,'.$invoiceId.',id,account_id,'.Auth::user()->account_id,
            'discount' => 'positive',
        ];

        /* There's a problem parsing the dates
        if (Request::get('is_recurring') && Request::get('start_date') && Request::get('end_date')) {
            $rules['end_date'] = 'after' . Request::get('start_date');
        }
        */

        return $rules;
    }
}
