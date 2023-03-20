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

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Request;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Vendor::class);
    }

    public function rules()
    {
        $rules = [];
        
        $rules['contacts.*.email'] = 'bail|nullable|distinct|sometimes|email';

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('vendors')->where('company_id', auth()->user()->company()->id);
        }
        
        $rules['currency_id'] = 'bail|required|exists:currencies,id';

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

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (!array_key_exists('currency_id', $input) || empty($input['currency_id'])) {
            $input['currency_id'] = auth()->user()->company()->settings->currency_id;
        }

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
        ];
    }
}
