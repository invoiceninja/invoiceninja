<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\BankTransactionRule;

use App\Http\Requests\Request;
use App\Models\Account;
use App\Models\BankTransactionRule;
use App\Utils\Traits\MakesHash;

class StoreBankTransactionRuleRequest extends Request
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

        return $user->can('create', BankTransactionRule::class) && $user->account->hasFeature(Account::FEATURE_API);
        ;
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /* Ensure we have a client name, and that all emails are unique*/
        $rules = [
            'name' => 'bail|required|string',
            'rules' => 'bail|array',
            'rules.*.operator' => 'bail|required|nullable',
            'rules.*.search_key' => 'bail|required|nullable',
            'rules.*.value' => 'bail|required|nullable',
            'auto_convert' => 'bail|sometimes|bool',
            'matches_on_all' => 'bail|sometimes|bool',
            'applies_to' => 'bail|sometimes|string|in:CREDIT,DEBIT',
            'on_credit_match' => 'bail|sometimes|in:create_payment,link_payment'
        ];

        $rules['category_id'] = 'bail|sometimes|nullable|exists:expense_categories,id,company_id,'.$user->company()->id.',is_deleted,0';
        $rules['vendor_id'] = 'bail|sometimes|nullable|exists:vendors,id,company_id,'.$user->company()->id.',is_deleted,0';
        $rules['client_id'] = 'bail|sometimes|nullable|exists:clients,id,company_id,'.$user->company()->id.',is_deleted,0';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}
