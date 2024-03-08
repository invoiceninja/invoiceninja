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

namespace App\Http\Requests\RecurringExpense;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateRecurringExpenseRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->recurring_expense);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/

        $rules['country_id'] = 'integer|nullable';

        $rules['contacts.*.email'] = 'nullable|distinct';

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('recurring_expenses')->where('company_id', auth()->user()->company()->id)->ignore($this->recurring_expense->id);
        }

        $rules['tax_amount1'] = 'numeric';
        $rules['tax_amount2'] = 'numeric';
        $rules['tax_amount3'] = 'numeric';
        $rules['category_id'] = 'bail|nullable|sometimes|exists:expense_categories,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
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
        /** @var \App\Models\User $user*/
        $user = auth()->user();

        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('next_send_date', $input) && is_string($input['next_send_date'])) {
            $input['next_send_date_client'] = $input['next_send_date'];
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        if (! array_key_exists('currency_id', $input) || strlen($input['currency_id']) == 0) {
            $input['currency_id'] = (string) $user->company()->settings->currency_id;
        }

        $this->replace($input);
    }
}
