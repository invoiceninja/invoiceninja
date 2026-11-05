<?php

/**
 * Invoice Ninja (https://paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Task;

use App\Http\Requests\Request;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Validation\Validator;

class SortTaskRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Task::class);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company_id = $user->company()->id;

        return [
            'status_ids' => 'bail|required|array',
            'status_ids.*' => 'bail|required|integer|exists:task_statuses,id,company_id,' . $company_id . ',is_deleted,0',
            'task_ids' => 'bail|required|array',
            'task_ids.*' => 'bail|array',
            'task_ids.*.*' => 'bail|required|integer|exists:tasks,id,company_id,' . $company_id,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var \App\Models\User $user */
            $user = auth()->user();
            $company_id = $user->company()->id;

            $status_ids = collect($this->input('status_ids', []));
            $task_ids = $this->input('task_ids', []);

            foreach (array_keys($task_ids) as $status_id) {
                if (! is_numeric($status_id)) {
                    $validator->errors()->add('task_ids', 'Invalid task status.');

                    continue;
                }

                $status_id = (int) $status_id;

                if (! TaskStatus::query()->where('id', $status_id)->where('company_id', $company_id)->where('is_deleted', 0)->exists()) {
                    $validator->errors()->add('task_ids', 'Invalid task status.');
                }

                if (! $status_ids->contains($status_id)) {
                    $validator->errors()->add('task_ids', 'Invalid task status.');
                }
            }
        });
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (isset($input['status_ids']) && is_array($input['status_ids'])) {
            $input['status_ids'] = array_map(
                fn ($status_id) => $this->decodeRequestId($status_id),
                $input['status_ids'],
            );
        }

        if (isset($input['task_ids']) && is_array($input['task_ids'])) {
            $task_ids = [];

            foreach ($input['task_ids'] as $status_key => $task_list) {
                $status_id = $this->decodeRequestId($status_key);

                if (! is_array($task_list)) {
                    $task_list = [];
                }

                $task_ids[$status_id] = array_map(
                    fn ($task_id) => $this->decodeRequestId($task_id),
                    $task_list,
                );
            }

            $input['task_ids'] = $task_ids;
        }

        $this->replace($input);
    }

    private function decodeRequestId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        try {
            return $this->decodePrimaryKey($value);
        } catch (\Exception) {
            return null;
        }
    }
}
