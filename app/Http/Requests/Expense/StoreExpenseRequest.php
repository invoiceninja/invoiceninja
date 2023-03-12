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

namespace App\Http\Requests\Expense;

use App\Http\Requests\Request;
use App\Models\Expense;
use App\Models\Project;
use App\Utils\Traits\MakesHash;
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
        $rules = [];

        if ($this->number) {
            $rules['number'] = Rule::unique('expenses')->where('company_id', auth()->user()->company()->id);
        }

        if ($this->client_id) {
            $rules['client_id'] = 'bail|sometimes|exists:clients,id,company_id,'.auth()->user()->company()->id;
        }

        $rules['category_id'] = 'bail|nullable|sometimes|exists:expense_categories,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (! array_key_exists('currency_id', $input) || strlen($input['currency_id']) == 0) {
            $input['currency_id'] = (string) auth()->user()->company()->settings->currency_id;
        }

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
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


        $this->replace($input);
    }

    public function messages()
    {
        return [
            // 'unique' => ctrans('validation.unique', ['attribute' => 'number']),
        ];
    }
}
