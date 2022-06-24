<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
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
            'invoices' => ['array', new PaymentAppliedValidAmount, new ValidCreditsPresentRule],
            'invoices.*.invoice_id' => 'distinct',
            'documents' => 'mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];

        if ($this->number) {
            $rules['number'] = Rule::unique('payments')->where('company_id', auth()->user()->company()->id)->ignore($this->payment->id);
        }

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['client_id'])) {
            unset($input['client_id']);
        }

        if (isset($input['amount'])) {
            unset($input['amount']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                if (array_key_exists('invoice_id', $input['invoices'][$key])) {
                    $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
                }
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
