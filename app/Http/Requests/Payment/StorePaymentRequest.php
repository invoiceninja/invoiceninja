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
use App\Http\ValidationRules\Credit\CreditsSumRule;
use App\Http\ValidationRules\Credit\ValidCreditsRules;
use App\Http\ValidationRules\Payment\ValidInvoicesRules;
use App\Http\ValidationRules\PaymentAmountsBalanceRule;
use App\Http\ValidationRules\ValidCreditsPresentRule;
use App\Http\ValidationRules\ValidPayableInvoicesRule;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Payment::class);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $invoices_total = 0;
        $credits_total = 0;

        if (isset($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);

                if (array_key_exists('amount', $value)) {
                    $invoices_total += $value['amount'];
                }
            }
        }

        if (isset($input['invoices']) && is_array($input['invoices']) === false) {
            $input['invoices'] = null;
        }

        if (isset($input['credits']) && is_array($input['credits']) !== false) {
            foreach ($input['credits'] as $key => $value) {
                if (array_key_exists('credit_id', $input['credits'][$key])) {
                    $input['credits'][$key]['credit_id'] = $value['credit_id'];
                    $credits_total += $value['amount'];
                }
            }
        }

        // if (array_key_exists('amount', $input))
        //     $input['amount'] = 0;

        if (isset($input['credits']) && is_array($input['credits']) === false) {
            $input['credits'] = null;
        }

        if (! isset($input['amount']) || $input['amount'] == 0) {
            $input['amount'] = $invoices_total - $credits_total;
        }

        // $input['is_manual'] = true;

        if (! isset($input['date'])) {
            $input['date'] = now()->format('Y-m-d');
        }

        $this->replace($input);
    }

    public function rules()
    {
        $rules = [
            'amount' => ['numeric', 'bail', new PaymentAmountsBalanceRule(), new ValidCreditsPresentRule()],
            'client_id' => 'bail|required|exists:clients,id',
            'invoices.*.invoice_id' => 'bail|required|distinct|exists:invoices,id',
            'invoices.*.amount' => 'bail|required',
            'invoices.*.invoice_id' => new ValidInvoicesRules($this->all()),
            'credits.*.credit_id' => 'bail|required|exists:credits,id',
            'credits.*.credit_id' => new ValidCreditsRules($this->all()),
            'credits.*.amount' => ['required', new CreditsSumRule($this->all())],
            'invoices' => new ValidPayableInvoicesRule(),
            'number' => ['nullable', 'bail', Rule::unique('payments')->where('company_id', auth()->user()->company()->id)],

        ];

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
}
