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
use App\Utils\Traits\ChecksEntityStatus;
class UpdateExpenseCategoryRequest extends Request
{
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

        $rules = [];

        if ($this->input('name')) 
            $rules['name'] = 'unique:expense_categories,name,'.$this->id.',id,company_id,'.$this->expense_category->company_id;

        return $rules;
    }

}
