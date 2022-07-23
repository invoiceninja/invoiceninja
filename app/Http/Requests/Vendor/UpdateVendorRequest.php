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
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->vendor);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/

        $rules['country_id'] = 'integer|nullable';
        
        if($this->number)
            $rules['number'] = Rule::unique('vendors')->where('company_id', auth()->user()->company()->id)->ignore($this->vendor->id);

        // if($this->id_number)   
        //     $rules['id_number'] = Rule::unique('vendors')->where('company_id', auth()->user()->company()->id)->ignore($this->vendor->id);

        $rules['contacts.*.email'] = 'nullable|distinct';
        // $rules['id_number'] = 'unique:vendors,id_number,'.$this->id.',id,company_id,'.auth()->user()->company()->id;

        return $rules;
    }

    public function messages()
    {
        return [
            'email' => ctrans('validation.email', ['attribute' => 'email']),
            'name.required' => ctrans('validation.required', ['attribute' => 'name']),
            'required' => ctrans('validation.required', ['attribute' => 'email']),
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}
