<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Company;

use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Models\ClientContact;
use App\Models\Company;

class StoreCompanyRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Company::class);
    }

    public function rules()
    {
        //$this->sanitize();
        $rules = [];

        //$rules['name'] = 'required';
        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000'; // max 10000kb
        $rules['settings'] = new ValidSettingsRule();

        return $rules;
    }


}

