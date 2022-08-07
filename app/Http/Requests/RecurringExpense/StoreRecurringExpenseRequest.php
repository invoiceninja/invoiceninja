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

namespace App\Http\Requests\RecurringExpense;

use App\Http\Requests\Request;
use App\Http\ValidationRules\RecurringExpense\UniqueRecurringExpenseNumberRule;
use App\Models\RecurringExpense;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreRecurringExpenseRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', RecurringExpense::class);
    }

    public function rules()
    {
        $rules = [];

        if ($this->number) {
            $rules['number'] = Rule::unique('recurring_expenses')->where('company_id', auth()->user()->company()->id);
        }

        if (! empty($this->client_id)) {
            $rules['client_id'] = 'bail|sometimes|exists:clients,id,company_id,'.auth()->user()->company()->id;
        }

        $rules['frequency_id'] = 'required|integer|digits_between:1,12';
        $rules['tax_amount1'] = 'numeric';
        $rules['tax_amount2'] = 'numeric';
        $rules['tax_amount3'] = 'numeric';

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('next_send_date', $input) && is_string($input['next_send_date'])) {
            $input['next_send_date_client'] = $input['next_send_date'];
        }

        if (array_key_exists('category_id', $input) && is_string($input['category_id'])) {
            $input['category_id'] = $this->decodePrimaryKey($input['category_id']);
        }

        if (! array_key_exists('currency_id', $input) || strlen($input['currency_id']) == 0) {
            $input['currency_id'] = (string) auth()->user()->company()->settings->currency_id;
        }

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
        }

        if (array_key_exists('foreign_amount', $input) && is_null($input['foreign_amount'])) {
            $input['foreign_amount'] = 0;
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
        ];
    }
}
