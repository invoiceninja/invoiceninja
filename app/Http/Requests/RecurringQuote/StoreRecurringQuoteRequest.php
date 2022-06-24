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
use App\Http\ValidationRules\Recurring\UniqueRecurringQuoteNumberRule;
use App\Models\Client;
use App\Models\RecurringQuote;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\UploadedFile;

class StoreRecurringQuoteRequest extends Request
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
        return auth()->user()->can('create', RecurringQuote::class);
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

        $rules['client_id'] = 'required|exists:clients,id,company_id,'.auth()->user()->company()->id;

        $rules['invitations.*.client_contact_id'] = 'distinct';

        $rules['frequency_id'] = 'required|integer|digits_between:1,12';

        $rules['number'] = new UniqueRecurringQuoteNumberRule($this->all());

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();
        $input = $this->decodePrimaryKeys($input);

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];

        if (isset($input['auto_bill'])) {
            $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
        } else {
            if ($client = Client::find($input['client_id'])) {
                $input['auto_bill'] = $client->getSetting('auto_bill');
                $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
            }
        }

        $this->replace($input);
    }

    private function setAutoBillFlag($auto_bill)
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }

    public function messages()
    {
        return [];
    }
}
