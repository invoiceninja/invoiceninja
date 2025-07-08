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

use App\Models\Expense;
use App\Http\Requests\Request;
use App\Models\Location;

class StoreLocationRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Location::class) || $user->can('create', Expense::class);
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        $rules['name'] = 'required|unique:locations,name,null,null,company_id,'.$user->companyId();

        $rules['client_id'] = 'required_without:vendor_id|nullable|integer|bail|exists:clients,id,company_id,'.$user->companyId();
        $rules['vendor_id'] = 'required_without:client_id|nullable|integer|bail|exists:vendors,id,company_id,'.$user->companyId();

        $rules['country_id'] = 'integer|bail|exists:countries,id';

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
        }


        $this->replace($input);
    }
}
