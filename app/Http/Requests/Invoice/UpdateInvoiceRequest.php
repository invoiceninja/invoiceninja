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
use App\Http\ValidationRules\Invoice\LockedInvoiceRule;
use App\Http\ValidationRules\Project\ValidProjectForClient;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends Request
{
    use MakesHash;
    use CleanLineItems;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->invoice);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

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

        // $rules['id'] = new LockedInvoiceRule($this->invoice);

        $rules['number'] = ['bail', 'sometimes', 'nullable', Rule::unique('invoices')->where('company_id', $user->company()->id)->ignore($this->invoice->id)];

        $rules['is_amount_discount'] = ['boolean'];
        $rules['client_id'] = ['bail', 'sometimes', Rule::in([$this->invoice->client_id])];
        $rules['line_items'] = 'array';
        $rules['discount'] = 'sometimes|numeric';
        $rules['project_id'] = ['bail', 'sometimes', new ValidProjectForClient($this->all())];
        $rules['tax_rate1'] = 'bail|sometimes|numeric';
        $rules['tax_rate2'] = 'bail|sometimes|numeric';
        $rules['tax_rate3'] = 'bail|sometimes|numeric';
        $rules['tax_name1'] = 'bail|sometimes|string|nullable';
        $rules['tax_name2'] = 'bail|sometimes|string|nullable';
        $rules['tax_name3'] = 'bail|sometimes|string|nullable';
        $rules['status_id'] = 'bail|sometimes|not_in:5'; //do not allow cancelled invoices to be modfified.
        $rules['exchange_rate'] = 'bail|sometimes|numeric';
        $rules['partial'] = 'bail|sometimes|nullable|numeric';
        $rules['partial_due_date'] = ['bail', 'sometimes', 'exclude_if:partial,0', Rule::requiredIf(fn () => $this->partial > 0), 'date', 'before:due_date'];


        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['id'] = $this->invoice->id;

        if(isset($input['partial']) && $input['partial'] == 0 && isset($input['partial_due_date'])) {
            $input['partial_due_date'] = '';
        }

        if (isset($input['line_items']) && is_array($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        if (array_key_exists('exchange_rate', $input) && is_null($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'id' => ctrans('texts.locked_invoice'),
            'status_id' => ctrans('texts.locked_invoice'),
        ];
    }
}
