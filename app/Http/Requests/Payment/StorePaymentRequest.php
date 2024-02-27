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
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Payment::class);
    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $input = $this->all();

        $invoices_total = 0;
        $credits_total = 0;

        if (isset($input['client_id']) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id'], true);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                if (is_string($value['invoice_id'])) {
                    $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
                }

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
                    $input['credits'][$key]['credit_id'] = $this->decodePrimaryKey($value['credit_id']);

                    $credits_total += $value['amount'];
                }
            }
        }

        if (isset($input['credits']) && is_array($input['credits']) === false) {
            $input['credits'] = null;
        }

        if (! isset($input['amount']) || $input['amount'] == 0) {
            $input['amount'] = $invoices_total - $credits_total;
        }

        if (! isset($input['date'])) {
            $input['date'] = now()->addSeconds($user->company()->utc_offset())->format('Y-m-d');
        }

        if (! isset($input['idempotency_key'])) {
            $input['idempotency_key'] = substr(sha1(json_encode($input)).time()."{$input['date']}{$input['amount']}{$user->id}", 0, 64);
        }

        $this->replace($input);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'amount' => ['numeric', 'bail', new PaymentAmountsBalanceRule(), new ValidCreditsPresentRule($this->all())],
            'client_id' => 'bail|required|exists:clients,id,company_id,'.$user->company()->id.',is_deleted,0',
            'invoices.*.invoice_id' => 'bail|required|distinct|exists:invoices,id',
            'invoices.*.amount' => 'bail|required',
            'invoices.*.invoice_id' => new ValidInvoicesRules($this->all()),
            'credits.*.credit_id' => 'bail|required|exists:credits,id',
            'credits.*.credit_id' => new ValidCreditsRules($this->all()),
            'credits.*.amount' => ['bail','required', new CreditsSumRule($this->all())],
            'invoices' => new ValidPayableInvoicesRule(),
            'number' => ['nullable', 'bail', Rule::unique('payments')->where('company_id', $user->company()->id)],
            'idempotency_key' => ['nullable', 'bail', 'string','max:64', Rule::unique('payments')->where('company_id', $user->company()->id)],
        ];

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
}
