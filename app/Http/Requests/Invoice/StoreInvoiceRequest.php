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

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Project\ValidProjectForClient;
use App\Models\Invoice;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Invoice::class);
    }

    public function rules()
    {
        $rules = [];

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        }else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
        }

        $rules['client_id'] = 'bail|required|exists:clients,id,company_id,'.$user->company()->id.',is_deleted,0';

        $rules['invitations.*.client_contact_id'] = 'distinct';

        $rules['number'] = ['bail', 'nullable', Rule::unique('invoices')->where('company_id', $user->company()->id)];

        $rules['project_id'] = ['bail', 'sometimes', new ValidProjectForClient($this->all())];
        $rules['is_amount_discount'] = ['boolean'];

        $rules['line_items'] = 'array';
        $rules['discount'] = 'sometimes|numeric';
        $rules['tax_rate1'] = 'bail|sometimes|numeric';
        $rules['tax_rate2'] = 'bail|sometimes|numeric';
        $rules['tax_rate3'] = 'bail|sometimes|numeric';
        $rules['tax_name1'] = 'bail|sometimes|string|nullable';
        $rules['tax_name2'] = 'bail|sometimes|string|nullable';
        $rules['tax_name3'] = 'bail|sometimes|string|nullable';
        $rules['exchange_rate'] = 'bail|sometimes|numeric';
        $rules['partial'] = 'bail|sometimes|nullable|numeric|gte:0';
        $rules['partial_due_date'] = ['bail', 'sometimes', 'exclude_if:partial,0', Rule::requiredIf(fn () => $this->partial > 0), 'date'];


        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['line_items']) && is_array($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        }

        if(isset($input['partial']) && $input['partial'] == 0 && isset($input['partial_due_date'])) {
            $input['partial_due_date'] = '';
        }

        $input['amount'] = 0;
        $input['balance'] = 0;

        if (array_key_exists('tax_rate1', $input) && is_null($input['tax_rate1'])) {
            $input['tax_rate1'] = 0;
        }
        if (array_key_exists('tax_rate2', $input) && is_null($input['tax_rate2'])) {
            $input['tax_rate2'] = 0;
        }
        if (array_key_exists('tax_rate3', $input) && is_null($input['tax_rate3'])) {
            $input['tax_rate3'] = 0;
        }
        if (array_key_exists('exchange_rate', $input) && is_null($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        $this->replace($input);
    }
}
