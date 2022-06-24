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

namespace App\Http\Requests\RecurringQuote;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UpdateRecurringQuoteRequest extends Request
{
    use ChecksEntityStatus;
    use CleanLineItems;
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->recurring_quote);
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

        if ($this->number) {
            $rules['number'] = Rule::unique('recurring_quotes')->where('company_id', auth()->user()->company()->id)->ignore($this->recurring_quote->id);
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();
        $input = $this->decodePrimaryKeys($input);

        if (isset($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        }

        if (isset($input['auto_bill'])) {
            $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        $this->replace($input);
    }

    /**
     * if($auto_bill == '')
     * off / optin / optout will reset the status of this field to off to allow
     * the client to choose whether to auto_bill or not.
     *
     * @param enum $auto_bill off/always/optin/optout
     *
     * @return bool
     */
    private function setAutoBillFlag($auto_bill) :bool
    {
        if ($auto_bill == 'always') {
            return true;
        }

        // if($auto_bill == '')
        // off / optin / optout will reset the status of this field to off to allow
        // the client to choose whether to auto_bill or not.

        return false;
    }
}
