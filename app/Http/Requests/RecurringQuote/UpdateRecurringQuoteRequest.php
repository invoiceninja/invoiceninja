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

namespace App\Http\Requests\RecurringQuote;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
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
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->recurring_quote);
    }

    public function rules()
    {
        $rules = [];

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
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
     * @param string $auto_bill off/always/optin/optout
     *
     * @return bool
     */
    private function setAutoBillFlag($auto_bill): bool
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
