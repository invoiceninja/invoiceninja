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

namespace App\Http\Requests\Quote;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Quote\UniqueQuoteNumberRule;
use App\Models\Quote;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends Request
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
        return auth()->user()->can('create', Quote::class);
    }

    public function rules()
    {
        $rules = [];

        $rules['client_id'] = 'required|exists:clients,id,company_id,'.auth()->user()->company()->id;

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        $rules['number'] = ['nullable', Rule::unique('quotes')->where('company_id', auth()->user()->company()->id)];
        $rules['discount'] = 'sometimes|numeric';

        $rules['is_amount_discount'] = ['boolean'];

        // $rules['number'] = new UniqueQuoteNumberRule($this->all());
        $rules['line_items'] = 'array';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        $input['amount'] = 0;
        $input['balance'] = 0;

        $this->replace($input);
    }
}
