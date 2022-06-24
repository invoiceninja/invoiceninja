<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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
    public function authorize() : bool
    {
        return auth()->user()->can('create', Invoice::class);
    }

    public function rules()
    {
        $rules = [];

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        if ($this->input('file') && is_array($this->input('file'))) {
            $documents = count($this->input('file'));

            foreach (range(0, $documents) as $index) {
                $rules['file.'.$index] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('file')) {
            $rules['file'] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        $rules['client_id'] = 'bail|required|exists:clients,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
        // $rules['client_id'] = ['required', Rule::exists('clients')->where('company_id', auth()->user()->company()->id)];

        $rules['invitations.*.client_contact_id'] = 'distinct';

        $rules['number'] = ['nullable', Rule::unique('invoices')->where('company_id', auth()->user()->company()->id)];

        $rules['project_id'] = ['bail', 'sometimes', new ValidProjectForClient($this->all())];
        $rules['is_amount_discount'] = ['boolean'];

        $rules['line_items'] = 'array';
        $rules['discount'] = 'sometimes|numeric';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['line_items']) && is_array($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
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

        $this->replace($input);
    }
}
