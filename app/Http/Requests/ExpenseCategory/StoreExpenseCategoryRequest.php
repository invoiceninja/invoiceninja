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

namespace App\Http\Requests\ExpenseCategory;

use App\Models\Expense;
use App\Http\Requests\Request;
use App\Models\ExpenseCategory;

class StoreExpenseCategoryRequest extends Request
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

        return $user->can('create', ExpenseCategory::class) || $user->can('create', Expense::class);
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        $rules['name'] = 'required|unique:expense_categories,name,null,null,company_id,'.$user->companyId();

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
        }

        $this->replace($input);
    }
}
