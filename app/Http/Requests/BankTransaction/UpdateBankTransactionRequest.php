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
use App\Utils\Traits\MakesHash;

class UpdateBankTransactionRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->bank_transaction);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/
        $rules = [
            'date' => 'bail|required|date',
            'amount' => 'numeric|required',
        ];

        if (isset($this->currency_id)) {
            $rules['currency_id'] = 'sometimes|exists:currencies,id';
        }

        if (isset($this->vendor_id)) {
            $rules['vendor_id'] = 'bail|required|exists:vendors,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        }

        // if (isset($this->expense_id)) {
        //     $rules['expense_id'] = 'bail|required|exists:expenses,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        // }

        $rules['bank_integration_id'] = 'bail|required|exists:bank_integrations,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';


        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();


        if (array_key_exists('baseType', $input) && strlen($input['baseType']) > 1) {
            $input['base_type'] = $input['baseType'];
        } //== 'deposit' ? 'CREDIT' : 'DEBIT';

        if (array_key_exists('vendor_id', $input) && strlen($input['vendor_id']) > 1) {
            $input['vendor_id'] = $this->decodePrimaryKey($input['vendor_id']);
        }

        // if (array_key_exists('expense_id', $input) && strlen($input['expense_id']) > 1) {
        //     $input['expense_id'] = $this->decodePrimaryKey($input['expense_id']);
        // }

        if (array_key_exists('ninja_category_id', $input) && strlen($input['ninja_category_id']) > 1) {
            $input['ninja_category_id'] = $this->decodePrimaryKey($input['ninja_category_id']);
        }

        if (array_key_exists('bank_integration_id', $input) && $input['bank_integration_id'] == "") {
            unset($input['bank_integration_id']);
        } elseif (array_key_exists('bank_integration_id', $input) && strlen($input['bank_integration_id']) > 1) {
            $input['bank_integration_id'] = $this->decodePrimaryKey($input['bank_integration_id']);
        }

        $this->replace($input);
    }
}
