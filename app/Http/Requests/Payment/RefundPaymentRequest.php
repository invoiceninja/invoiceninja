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
use App\Http\ValidationRules\ValidRefundableInvoices;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;

class RefundPaymentRequest extends Request
{
	use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }
    
    protected function prepareForValidation()
    {
        $input = $this->all();

        if(!isset($input['gateway_refund']))
        	$input['gateway_refund'] = false;

        if(isset($input['id']))
        	$input['id'] = $this->decodePrimaryKey($input['id']);

	    $this->replace($input);
    }

    public function rules()
    {
        $rules = [
            'id' => 'required',
            'refunded' => 'numeric',
            'date' => 'required',
            'invoices.*.invoice_id' => 'required',
            'invoices.*.refunded' => 'required',
            'invoices' => new ValidRefundableInvoices(),
        ];

        return $rules;
    }
}
