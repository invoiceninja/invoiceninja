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
use App\Models\Project;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends Request
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
        return auth()->user()->can('edit', $this->expense);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/
        $rules = [];
     
        if (isset($this->number)) {
            $rules['number'] = Rule::unique('expenses')->where('company_id', auth()->user()->company()->id)->ignore($this->expense->id);
        }

        if ($this->client_id) {
            $rules['client_id'] = 'bail|sometimes|exists:clients,id,company_id,'.auth()->user()->company()->id;
        }

        $rules['category_id'] = 'bail|sometimes|nullable|exists:expense_categories,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        $rules['transaction_id'] = 'bail|sometimes|nullable|exists:bank_transactions,id,company_id,'.auth()->user()->company()->id;
        $rules['invoice_id'] = 'bail|sometimes|nullable|exists:invoices,id,company_id,'.auth()->user()->company()->id;


        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        if (! array_key_exists('currency_id', $input) || strlen($input['currency_id']) == 0) {
            $input['currency_id'] = (string) auth()->user()->company()->settings->currency_id;
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
}
