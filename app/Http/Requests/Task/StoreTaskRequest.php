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

namespace App\Http\Requests\Task;

use App\DataMapper\TaskSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Task\UniqueTaskNumberRule;
use App\Http\ValidationRules\ValidTaskGroupSettingsRule;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Task::class);
    }

    public function rules()
    {
        $rules = [];
        /* Ensure we have a client name, and that all emails are unique*/
        //$rules['name'] = 'required|min:1';
        //$rules['client_id'] = 'required|exists:clients,id,company_id,'.auth()->user()->company()->id;

       // $rules['number'] = new UniqueTaskNumberRule($this->all());


        return $rules;
    }

    protected function prepareForValidation()
    {
         $input = $this->all();

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }

        if (array_key_exists('client_id', $input) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('project_id', $input) && is_string($input['project_id'])) {
            $input['project_id'] = $this->decodePrimaryKey($input['project_id']);
        }        

        if (array_key_exists('invoice_id', $input) && is_string($input['invoice_id'])) {
            $input['invoice_id'] = $this->decodePrimaryKey($input['invoice_id']);
        }    

         $this->replace($input);
    }

    // public function messages()
    // {
    //     // return [
    //     //     'unique' => ctrans('validation.unique', ['attribute' => 'email']),
    //     //     //'required' => trans('validation.required', ['attribute' => 'email']),
    //     //     'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
    //     // ];
    // }
}
