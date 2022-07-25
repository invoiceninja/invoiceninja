<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Expense;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
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
        $rules = [];
        // $rules['country_id'] = 'integer|nullable';

        // $rules['contacts.*.email'] = 'nullable|distinct';

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('expenses')->where('company_id', auth()->user()->company()->id)->ignore($this->expense->id);
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

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('category_id', $input) && is_string($input['category_id'])) {
            $input['category_id'] = $this->decodePrimaryKey($input['category_id']);
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        if (! array_key_exists('currency_id', $input) || strlen($input['currency_id']) == 0) {
            $input['currency_id'] = (string) auth()->user()->company()->settings->currency_id;
        }

        $this->replace($input);
    }
}
