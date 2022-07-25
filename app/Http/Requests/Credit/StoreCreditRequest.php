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

namespace App\Http\Requests\Credit;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Credit\UniqueCreditNumberRule;
use App\Http\ValidationRules\Credit\ValidInvoiceCreditRule;
use App\Models\Credit;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreCreditRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create', Credit::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
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

        $rules['client_id'] = 'required|exists:clients,id,company_id,'.auth()->user()->company()->id;

        // $rules['number'] = new UniqueCreditNumberRule($this->all());
        $rules['number'] = ['nullable', Rule::unique('credits')->where('company_id', auth()->user()->company()->id)];
        $rules['discount'] = 'sometimes|numeric';
        $rules['is_amount_discount'] = ['boolean'];

        if ($this->invoice_id) {
            $rules['invoice_id'] = new ValidInvoiceCreditRule();
        }

        $rules['line_items'] = 'array';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }

        $input = $this->decodePrimaryKeys($input);

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        //$input['line_items'] = json_encode($input['line_items']);
        $this->replace($input);
    }
}
