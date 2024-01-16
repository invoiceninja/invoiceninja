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

namespace App\Http\Requests\Expense;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class BulkExpenseRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'ids' => ['required','bail','array', Rule::exists('expenses', 'id')->where('company_id', $user->company()->id)],
            'category_id' => ['sometimes', 'bail', Rule::exists('expense_categories', 'id')->where('company_id', $user->company()->id)],
            'action' => 'in:archive,restore,delete,bulk_categorize',
        ];


    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        if (isset($input['category_id'])) {
            $input['category_id'] = $this->transformKeys($input['category_id']);
        }

        $this->replace($input);
    }
}
