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

namespace App\Http\Requests\Task;

use App\Http\Requests\Request;
use App\Models\Project;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
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

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('tasks')->where('company_id', auth()->user()->company()->id);
        }

        if (isset($this->client_id)) {
            $rules['client_id'] = 'bail|required|exists:clients,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        }

        if (isset($this->project_id)) {
            $rules['project_id'] = 'bail|required|exists:projects,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        }

        $rules['timelog'] = ['bail','array',function ($attribute, $values, $fail) {
            foreach ($values as $k) {
                if (!is_int($k[0]) || !is_int($k[1])) {
                    $fail('The '.$attribute.' - '.print_r($k, 1).' is invalid. Unix timestamps only.');
                }
            }

            if (!$this->checkTimeLog($values)) {
                $fail('Please correct overlapping values');
            }
        }];
        
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

    public function prepareForValidation()
    {
        $input = $this->all();
        $input = $this->decodePrimaryKeys($this->all());

        if (array_key_exists('status_id', $input) && is_string($input['status_id'])) {
            $input['status_id'] = $this->decodePrimaryKey($input['status_id']);
        }

        /* Ensure the project is related */
        if (array_key_exists('project_id', $input) && isset($input['project_id'])) {
            $project = Project::withTrashed()->where('id', $input['project_id'])->company()->first();
            ;
            if ($project) {
                $input['client_id'] = $project->client_id;
            } else {
                unset($input['project_id']);
            }
        }

        $this->replace($input);
    }
}
