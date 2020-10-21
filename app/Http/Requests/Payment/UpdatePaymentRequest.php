<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Http\ValidationRules\PaymentAppliedValidAmount;
use App\Http\ValidationRules\ValidCreditsPresentRule;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends Request
{
    use ChecksEntityStatus;
    use MakesHash;

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
        
       $rules = [
            'number' => 'nullable|unique:payments,number,'.$this->id.',id,company_id,'.$this->payment->company_id,
            'invoices' => ['array', new PaymentAppliedValidAmount, new ValidCreditsPresentRule],
            'invoices.*.invoice_id' => 'distinct',
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (isset($input['client_id'])) {
            unset($input['client_id']);
        }

        if (isset($input['amount'])) {
            unset($input['amount']);
        }

        // if (isset($input['type_id'])) {
        //     unset($input['type_id']);
        // }

        // if (isset($input['date'])) {
        //     unset($input['date']);
        // }

        // if (isset($input['transaction_reference'])) {
        //     unset($input['transaction_reference']);
        // }

        if (isset($input['number'])) {
            unset($input['number']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
            }
        }
        $this->replace($input);
    }

    public function messages()
    {
        return [
            'distinct' => 'Attemping duplicate payment on the same invoice Invoice',
        ];
    }
}
