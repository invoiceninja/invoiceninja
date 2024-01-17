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

namespace App\Http\Requests\BankTransactionRule;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class UpdateBankTransactionRuleRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->bank_transaction_rule);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/
        $rules = [
            'name' => 'bail|required|string',
            'rules' => 'bail|array',
            'rules.*.operator' => 'bail|required|nullable',
            'rules.*.search_key' => 'bail|required|nullable',
            'rules.*.value' => 'bail|required|nullable',
            'auto_convert' => 'bail|sometimes|bool',
            'matches_on_all' => 'bail|sometimes|bool',
            'applies_to' => 'bail|sometimes|string',
        ];

        if (isset($this->category_id)) {
            $rules['category_id'] = 'bail|sometimes|exists:expense_categories,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        }

        if (isset($this->vendor_id)) {
            $rules['vendor_id'] = 'bail|sometimes|exists:vendors,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        }

        if (isset($this->client_id)) {
            $rules['client_id'] = 'bail|sometimes|exists:clients,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        }


        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}
