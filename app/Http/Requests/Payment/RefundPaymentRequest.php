<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Models\Payment;

class RefundPaymentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->payment);
    }
    
    protected function prepareForValidation()
    {
        $input = $this->all();

	    $this->replace($input);
    }

    public function rules()
    {
        $rules = [
            'id' => 'required',
            'refunded' => 'numeric',
            'date' => 'required',
            'invoices.*.invoice_id' => 'required',
            'invoices.*.amount' => 'required',
            'credits.*.credit_id' => 'required',
            'credits.*.amount' => 'required',
            'invoices' => new ValidPayableInvoicesRule(),
            'number' => 'nullable',
        ];

        return $rules;
    }
}
