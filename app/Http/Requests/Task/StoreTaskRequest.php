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
        
        $rules['number'] = Rule::unique('tasks')
                                ->where('company_id', auth()->user()->company()->id);
    
       return $this->globalRules($rules);
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($this->all()); 

        if (array_key_exists('status_id', $input) && is_string($input['status_id'])) {
            $input['status_id'] = $this->decodePrimaryKey($input['status_id']);
        }

        $this->replace($input);
    }

}
