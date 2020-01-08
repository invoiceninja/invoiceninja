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
use App\Utils\Traits\ChecksEntityStatus;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends Request
{
    use ChecksEntityStatus;
    
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->payment);
    }


    public function rules()
    {
        return [
            'amount' => [new PaymentAppliedValidAmount(),new ValidCreditsPresentRule()],
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if(array_key_exists('client_id', $input)) 
            unset($input['client_id']);
        
        if(array_key_exists('amount', $input)) 
            unset($input['amount']);

        if(array_key_exists('type_id', $input)) 
                    unset($input['type_id']);

        if(array_key_exists('date', $input)) 
                    unset($input['date']);

        if(array_key_exists('transaction_reference', $input)) 
                    unset($input['transaction_reference']);

        if(array_key_exists('amnumberount', $input)) 
                    unset($input['number']);


        $this->replace($input);
    }
}
