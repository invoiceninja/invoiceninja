<?php namespace app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Validation\Factory;
use App\Models\Invoice;

class CreatePaymentRequest extends Request
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
        $input = $this->input();
        $invoice = Invoice::scope($input['invoice'])->firstOrFail();
            
        $rules = array(
            'client' => 'required',
            'invoice' => 'required',
            'amount' => 'required|less_than:{$invoice->balance}|positive',
        );

        if ($input['payment_type_id'] == PAYMENT_TYPE_CREDIT) {
            $rules['payment_type_id'] = 'has_credit:'.$input['client'].','.$input['amount'];
        }

        return $rules;
    }
}
