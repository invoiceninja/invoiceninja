<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\ExpenseCategory;

use App\Http\Requests\Request;
use App\Http\ValidationRules\ExpenseCategory\UniqueExpenseCategoryNumberRule;
use App\Http\ValidationRules\ValidExpenseCategoryGroupSettingsRule;
use App\Models\ExpenseCategory;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', ExpenseCategory::class);
    }

    public function rules()
    {
        $rules = [];
        $rules['name'] = 'unique:expense_categories,name,'.$this->id.',id,company_id,'.$this->company_id;;


        return $rules;
    }

    protected function prepareForValidation()
    {
        // $input = $this->all();


        // $this->replace($input);
    }

    // public function messages()
    // {
    //     return [
    //         'unique' => ctrans('validation.unique', ['attribute' => 'email']),
    //         //'required' => trans('validation.required', ['attribute' => 'email']),
    //         'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
    //     ];
    // }
}
