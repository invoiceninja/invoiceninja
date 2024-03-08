<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->payment);
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'invoices' => ['array', new PaymentAppliedValidAmount($this->all()), new ValidCreditsPresentRule($this->all())],
            'invoices.*.invoice_id' => 'distinct',
        ];

        if ($this->number) {
            $rules['number'] = Rule::unique('payments')->where('company_id', $user->company()->id)->ignore($this->payment->id);
        }

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        }else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
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
                if(isset($input['invoices'][$key]['invoice_id'])) {
                    // if (array_key_exists('invoice_id', $input['invoices'][$key])) {
                    $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
                }
            }
        }

        if (isset($input['credits']) && is_array($input['credits']) !== false) {
            foreach ($input['credits'] as $key => $value) {
                // if (array_key_exists('credits', $input['credits'][$key])) {
                if (isset($input['credits'][$key]['credit_id'])) {
                    $input['credits'][$key]['credit_id'] = $this->decodePrimaryKey($value['credit_id']);
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
