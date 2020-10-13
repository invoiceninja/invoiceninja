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
use App\Http\ValidationRules\IsDeletedRule;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->expense_category);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/
        $rules = [];

        if ($this->input('number')) {
            $rules['name'] = 'unique:expense_categories,name,'.$this->id.',id,company_id,'.$this->expense_category->name;
        }

        return $rules;
    }

    // public function messages()
    // {
    //     return [
    //         'unique' => ctrans('validation.unique', ['attribute' => 'email']),
    //         'email' => ctrans('validation.email', ['attribute' => 'email']),
    //         'name.required' => ctrans('validation.required', ['attribute' => 'name']),
    //         'required' => ctrans('validation.required', ['attribute' => 'email']),
    //     ];
    // }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }
}
