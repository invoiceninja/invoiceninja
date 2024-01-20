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

class ImportBankTransactionsRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $rules = [
            'transactions' => 'bail|array',
            'transactions.*.id' => 'bail|required',
            'transactions.*.invoice_ids' => 'nullable|string|sometimes',
            'transactions.*.ninja_category_id' => 'nullable|string|sometimes'
        ];

        $rules['transactions.*.vendor_id'] = 'bail|sometimes|exists:vendors,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';

        return $rules;
    }

    public function prepareForValidation()
    {
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

            // $input = $this->decodePrimaryKeys($input);
        }

        $this->replace($inputs);
    }
}
