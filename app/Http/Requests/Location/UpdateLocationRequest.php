<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Location;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends Request
{
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->location);
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        if ($this->input('name')) {
            $rules['name'] = Rule::unique('locations')->where('company_id', $user->company()->id)->ignore($this->location->id);
        }


        $rules['client_id'] = 'required_without:vendor_id|nullable|integer|bail|exists:clients,id,company_id,'.$user->companyId();
        $rules['vendor_id'] = 'required_without:client_id|nullable|integer|bail|exists:vendors,id,company_id,'.$user->companyId();

        $rules['country_id'] = 'integer|exists:countries,id';

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}
