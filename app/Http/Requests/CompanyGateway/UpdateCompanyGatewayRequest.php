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

namespace App\Http\Requests\CompanyGateway;

use App\Http\Requests\Request;
use App\Models\Company;

class UpdateCompanyGatewayRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $this->sanitize();

        $rules = [
            'gateway_key' => 'required',
        ];
        
        return $rules;
    }


    public function sanitize()
    {
        $input = $this->all();

        $input['config'] = encrypt($input['config']);

        $this->replace($input);

        return $this->all();
    }

}