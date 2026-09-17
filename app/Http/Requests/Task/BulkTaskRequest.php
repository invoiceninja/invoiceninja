<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Task;

use App\Http\Requests\Request;
use App\Models\Task;
use Illuminate\Validation\Rule;

class BulkTaskRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'action' => 'required|string|in:archive,restore,delete,bulk_update,template,start,stop',
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool',
            'ids' => ['required','bail','array'],
            'column' => ['required_if:action,bulk_update', 'string', Rule::in(\App\Models\Task::$bulk_update_columns)],
            'new_value' => ['required_if:action,bulk_update|string'],
        ];

    }

    public function withValidator($validator)
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $validator->after(function ($validator) {

            if ($this->action == 'bulk_update') {
                
                $user = auth()->user();

                $permissions = Task::withTrashed()
                                ->whereIn('id', $this->transformKeys($this->ids))
                                ->get()
                                ->first(function ($task) use ($user) {
                                    return $user->cannot('edit', $task);
                                });       

                if ($permissions->isEmpty()) {
                    $validator->errors()->add('ids', 'You are not authorized to update these tasks.');
                }
            
            }
        });

    }
}
