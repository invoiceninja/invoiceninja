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

namespace App\Http\Requests\Expense;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkExpenseRequest extends Request
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
            'action' => 'required|string|in:archive,restore,delete,bulk_update,bulk_categorize,template',
            'ids' => ['required','bail','array', Rule::exists('expenses', 'id')->where('company_id', $user->company()->id)],
            'category_id' => ['sometimes', 'bail', Rule::exists('expense_categories', 'id')->where('company_id', $user->company()->id)],
            'column' => ['required_if:action,bulk_update', 'string', Rule::in(\App\Models\Expense::$bulk_update_columns)],
            'new_value' => ['required_if:action,bulk_update|string'],
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string|required_if:action,template',
            'send_email' => 'sometimes|bool',
        ];

    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('action') !== 'bulk_update') {
                return;
            }

            $column = $this->input('column');

            if (! in_array($column, ['project_id', 'client_id'], true)) {
                return;
            }

            $entity_id = $this->decodePrimaryKey($this->input('new_value'), true);

            if (! is_int($entity_id)) {
                $validator->errors()->add('new_value', 'The selected new value is invalid.');

                return;
            }

            /** @var \App\Models\User $user */
            $user = auth()->user();

            $entity_exists = $column === 'project_id'
                ? Project::withTrashed()
                    ->where('id', $entity_id)
                    ->where('company_id', $user->company()->id)
                    ->where('is_deleted', false)
                    ->exists()
                : Client::withTrashed()
                    ->where('id', $entity_id)
                    ->where('company_id', $user->company()->id)
                    ->where('is_deleted', false)
                    ->exists();

            if (! $entity_exists) {
                $validator->errors()->add('new_value', 'The selected new value is invalid.');
            }
        });
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        if (isset($input['category_id'])) {
            $input['category_id'] = $this->transformKeys($input['category_id']);
        }

        if (isset($input['newValue'])) {
            $input['new_value'] = $input['newValue'];
        }

        $this->replace($input);
    }
}
