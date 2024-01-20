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

namespace App\Http\Requests\BankTransaction;

use App\Http\Requests\Request;
use App\Models\BankTransaction;
use App\Models\Payment;

class MatchBankTransactionRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin() || $user->can('create', BankTransaction::class) || $user->hasPermission('edit_bank_transaction');
    }

    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'transactions' => 'bail|array',
            'transactions.*.invoice_ids' => 'nullable|string|sometimes',
        ];

        $rules['transactions.*.ninja_category_id'] = 'bail|nullable|sometimes|exists:expense_categories,id,company_id,'.$user->company()->id.',is_deleted,0';
        $rules['transactions.*.vendor_id'] = 'bail|nullable|sometimes|exists:vendors,id,company_id,'.$user->company()->id.',is_deleted,0';
        $rules['transactions.*.id'] = 'bail|required|exists:bank_transactions,id,company_id,'.$user->company()->id.',is_deleted,0';
        $rules['transactions.*.payment_id'] = 'bail|sometimes|nullable|exists:payments,id,company_id,'.$user->company()->id.',is_deleted,0';

        return $rules;
    }

    public function prepareForValidation()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $inputs = $this->all();

        foreach ($inputs['transactions'] as $key => $input) {
            if (array_key_exists('id', $inputs['transactions'][$key])) {
                $inputs['transactions'][$key]['id'] = $this->decodePrimaryKey($input['id']);
            }

            if (array_key_exists('ninja_category_id', $inputs['transactions'][$key]) && strlen($inputs['transactions'][$key]['ninja_category_id']) >= 1) {
                $inputs['transactions'][$key]['ninja_category_id'] = $this->decodePrimaryKey($inputs['transactions'][$key]['ninja_category_id']);
            }

            if (array_key_exists('vendor_id', $inputs['transactions'][$key]) && strlen($inputs['transactions'][$key]['vendor_id']) >= 1) {
                $inputs['transactions'][$key]['vendor_id'] = $this->decodePrimaryKey($inputs['transactions'][$key]['vendor_id']);
            }

            if (array_key_exists('payment_id', $inputs['transactions'][$key]) && strlen($inputs['transactions'][$key]['payment_id']) >= 1) {
                $inputs['transactions'][$key]['payment_id'] = $this->decodePrimaryKey($inputs['transactions'][$key]['payment_id']);
                $p = Payment::withTrashed()->where('company_id', $user->company()->id)->where('id', $inputs['transactions'][$key]['payment_id'])->first();

                /*Ensure we don't relink an existing payment*/
                if (!$p || is_numeric($p->transaction_id)) {
                    unset($inputs['transactions'][$key]);
                }
            }

        }

        $this->replace($inputs);
    }
}
