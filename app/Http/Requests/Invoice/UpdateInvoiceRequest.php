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
use App\Http\ValidationRules\Invoice\InvoiceBalanceSanity;
use App\Http\ValidationRules\Invoice\LockedInvoiceRule;
use App\Http\ValidationRules\Project\ValidProjectForClient;
use App\Models\Invoice;
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
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->invoice);
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

        $rules['id'] = new LockedInvoiceRule($this->invoice);

        if($this->number)
            $rules['number'] = Rule::unique('invoices')->where('company_id', auth()->user()->company()->id)->ignore($this->invoice->id);

        $rules['is_amount_discount'] = ['boolean'];
        
        $rules['line_items'] = 'array';
        $rules['discount']  = 'sometimes|numeric';
        $rules['project_id'] =  ['bail', 'sometimes', new ValidProjectForClient($this->all())];

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['id'] = $this->invoice->id;
        
        if (isset($input['line_items']) && is_array($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        }
        
        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'id' => ctrans('text.locked_invoice'),
        ];
    }
}
