<?php namespace app\Http\Requests;

use Auth;
use App\Http\Requests\Request;
use Illuminate\Validation\Factory;
use App\Models\Invoice;

class UpdateInvoiceRequest extends Request
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
        if ($this->action == ACTION_ARCHIVE) {
            return [];
        }

        $publicId = $this->route('invoices');
        $invoiceId = Invoice::getPrivateId($publicId);

        $rules = [
            'invoice_items' => 'required|valid_invoice_items',
            'invoice_number' => 'unique:invoices,invoice_number,'.$invoiceId.',id,account_id,'.Auth::user()->account_id,
            'discount' => 'positive',
        ];

        return $rules;
    }
}
