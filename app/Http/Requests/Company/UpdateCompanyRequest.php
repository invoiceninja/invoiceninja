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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('edit', $this->company);

    }


    public function rules()
    {
        
        return [
            'company_logo' => 'mimes:jpeg,jpg,png,gif|max:10000', // max 10000kb
            'industry_id' => 'integer|nullable',
            'size_id' => 'integer|nullable',
            'country_id' => 'integer|nullable',
            'work_email' => 'email|nullable',
           // 'settings' => 'json',
        ];
    }
    
}


