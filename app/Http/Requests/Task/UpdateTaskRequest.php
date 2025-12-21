<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Task;

use App\Http\Requests\Request;
use App\Models\Project;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Auth\Access\AuthorizationException;
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
    public function authorize(): bool
    {
        //prevent locked tasks from updating
        if ($this->task->invoice_id && $this->task->company->invoice_task_lock) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->task);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('tasks')->where('company_id', $user->company()->id)->ignore($this->task->id);
        }

        if (isset($this->client_id)) {
            $rules['client_id'] = 'bail|required|exists:clients,id,company_id,'.$user->company()->id.',is_deleted,0';
        }

        if (isset($this->project_id)) {
            $rules['project_id'] = 'bail|required|exists:projects,id,company_id,'.$user->company()->id.',is_deleted,0';
        }

        $rules['hash'] = 'bail|sometimes|string|nullable';

        $rules['time_log'] = ['bail', function ($attribute, $values, $fail) {

            if (is_string($values)) {
                $values = json_decode($values, true);
            }

            if (!is_array($values)) {
                $fail('The '.$attribute.' must be a valid array.');
                return;
            }

            foreach ($values as $key => $k) {
                
                // Check if this is an array
                if (!is_array($k)) {
                    return $fail('Time log entry at position '.$key.' must be an array.');
                }
                
                // Check for associative array (has string keys)
                if (array_keys($k) !== range(0, count($k) - 1)) {
                    return $fail('Time log entry at position '.$key.' uses invalid format. Expected: [unix_start, unix_end, description, billable]. Received associative array with keys: '.implode(', ', array_keys($k)));
                }
                
                // Ensure minimum required elements exist
                if (!isset($k[0]) || !isset($k[1])) {
                    return $fail('Time log entry at position '.$key.' must have at least 2 elements: [start_timestamp, end_timestamp].');
                }
                
                // Validate types for required elements
                if (!is_int($k[0]) || !is_int($k[1])) {
                    return $fail('Time log entry at position '.$key.' is invalid. Elements [0] and [1] must be Unix timestamps (integers). Received: '.print_r($k, true));
                }

                // Validate max elements
                if(count($k) > 4) {
                    return $fail('Time log entry at position '.$key.' can only have up to 4 elements. Received '.count($k).' elements.');
                }

                // Validate optional element [2] (description)
                if (isset($k[2]) && !is_string($k[2])) {
                    return $fail('Time log entry at position '.$key.': element [2] (description) must be a string. Received: '.gettype($k[2]));
                }
                
                // Validate optional element [3] (billable)
                if(isset($k[3]) && !is_bool($k[3])) {
                    return $fail('Time log entry at position '.$key.': element [3] (billable) must be a boolean. Received: '.gettype($k[3]));
                }
            }

            if (!$this->checkTimeLog($values)) {
                return $fail('Please correct overlapping values');
            }
        }];

        $rules['file'] = 'bail|sometimes|array';
        $rules['file.*'] = $this->fileValidation();
        

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->decodePrimaryKeys($this->all());

        if ($this->file('file') instanceof \Illuminate\Http\UploadedFile) {
            $this->files->set('file', [$this->file('file')]);
        }

        if (array_key_exists('status_id', $input) && is_string($input['status_id'])) {
            $input['status_id'] = $this->decodePrimaryKey($input['status_id']);
        }

        if (isset($input['documents'])) {
            unset($input['documents']);
        }

        /* Ensure the project is related */
        if (array_key_exists('project_id', $input) && isset($input['project_id'])) {
            $project = Project::withTrashed()->where('id', $input['project_id'])->company()->first();

            if ($project) {
                $input['client_id'] = $project->client_id;
            } else {
                unset($input['project_id']);
            }
        }

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
        }


        if(isset($input['time_log']) &&is_string($input['time_log'])) {
            $input['time_log'] = json_decode($input['time_log'], true);
        }

        if(isset($input['time_log']) && is_array($input['time_log'])) {
        
            $time_logs = $input['time_log'];

            foreach($time_logs as &$time_log) {
 
                if (is_string($time_log)) {
                    continue; //catch if it isn't even a proper time log  
                }

                $time_log[0] = intval($time_log[0] ?? 0);
                $time_log[1] = intval($time_log[1] ?? 0);
                $time_log[2] = strval($time_log[2] ?? '');
                $time_log[3] = boolval($time_log[3] ?? true);

            }

            $input['time_log'] = json_encode($time_logs);

        }
        
        if (isset($input['project_id']) && isset($input['client_id'])) {
            $search_project_with_client = Project::withTrashed()->where('id', $input['project_id'])->where('client_id', $input['client_id'])->company()->doesntExist();

            if ($search_project_with_client) {
                unset($input['project_id']);
            }

        }

        if (!isset($input['time_log']) || empty($input['time_log']) || $input['time_log'] == '{}' || $input['time_log'] == '[""]') {
            $input['time_log'] = json_encode([]);
        }

        $this->replace($input);
    }


    protected function failedAuthorization()
    {
        throw new AuthorizationException(ctrans('texts.task_update_authorization_error'));
    }
}
