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

namespace App\Http\Requests\Expense;

use App\Http\Requests\Request;
use App\Http\ValidationRules\IsDeletedRule;
use App\Http\ValidationRules\ValidExpenseGroupSettingsRule;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends Request
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
        return auth()->user()->can('edit', $this->expense);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/

        $rules['country_id'] = 'integer|nullable';
        //$rules['id_number'] = 'unique:clients,id_number,,id,company_id,' . auth()->user()->company()->id;
        $rules['contacts.*.email'] = 'nullable|distinct';

        if ($this->input('number')) {
            $rules['number'] = 'unique:expenses,number,'.$this->id.',id,company_id,'.$this->expense->company_id;
        }

        return $this->globalRules($rules);
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            'email' => ctrans('validation.email', ['attribute' => 'email']),
            'name.required' => ctrans('validation.required', ['attribute' => 'name']),
            'required' => ctrans('validation.required', ['attribute' => 'email']),
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('category_id', $input) && is_string($input['category_id'])) {
            $input['category_id'] = $this->decodePrimaryKey($input['category_id']);
        }

        $this->replace($input);
    }
}
