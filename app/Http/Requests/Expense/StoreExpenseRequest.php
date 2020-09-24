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

use App\DataMapper\ExpenseSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Expense\UniqueExpenseNumberRule;
use App\Http\ValidationRules\ValidExpenseGroupSettingsRule;
use App\Models\Expense;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Expense::class);
    }

    public function rules()
    {

        /* Ensure we have a client name, and that all emails are unique*/
        //$rules['name'] = 'required|min:1';
        $rules['id_number'] = 'unique:expenses,id_number,'.$this->id.',id,company_id,'.$this->company_id;
        //$rules['settings'] = new ValidExpenseGroupSettingsRule();
        $rules['contacts.*.email'] = 'nullable|distinct';

        $rules['number'] = new UniqueExpenseNumberRule($this->all());

        // $contacts = request('contacts');

        // if (is_array($contacts)) {
        //     for ($i = 0; $i < count($contacts); $i++) {

        //         //$rules['contacts.' . $i . '.email'] = 'nullable|email|distinct';
        //     }
        // }

        return $rules;
    }

    protected function prepareForValidation()
    {
        // $input = $this->all();


        // $this->replace($input);
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            //'required' => trans('validation.required', ['attribute' => 'email']),
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
        ];
    }
}
