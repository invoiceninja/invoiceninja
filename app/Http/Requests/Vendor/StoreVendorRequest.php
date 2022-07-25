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

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidVendorGroupSettingsRule;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Vendor::class);
    }

    public function rules()
    {

        /* Ensure we have a client name, and that all emails are unique*/
        //$rules['name'] = 'required|min:1';
        // $rules['id_number'] = 'unique:vendors,id_number,'.$this->id.',id,company_id,'.auth()->user()->company()->id;
        //$rules['settings'] = new ValidVendorGroupSettingsRule();

        $rules['contacts.*.email'] = 'bail|nullable|distinct|sometimes|email';

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('vendors')->where('company_id', auth()->user()->company()->id);
        }

        // if (isset($this->id_number)) {
        //     $rules['id_number'] = Rule::unique('vendors')->where('company_id', auth()->user()->company()->id);
        // }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }

    public function messages()
    {
        return [
            // 'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            //'required' => trans('validation.required', ['attribute' => 'email']),
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
        ];
    }
}
