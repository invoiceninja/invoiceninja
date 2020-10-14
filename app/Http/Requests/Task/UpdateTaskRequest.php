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

use App\Http\Requests\Request;
use App\Http\ValidationRules\IsDeletedRule;
use App\Http\ValidationRules\ValidTaskGroupSettingsRule;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends Request
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
        return auth()->user()->can('edit', $this->task);
    }

    public function rules()
    {
        $rules = [];
        /* Ensure we have a client name, and that all emails are unique*/

        if ($this->input('number')) {
            $rules['number'] = 'unique:tasks,number,'.$this->id.',id,company_id,'.$this->taskss->company_id;
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
}
