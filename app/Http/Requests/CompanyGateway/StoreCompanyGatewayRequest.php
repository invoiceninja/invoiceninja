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
use App\Http\ValidationRules\ValidCompanyGatewayFeesAndLimitsRule;
use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;

class StoreCompanyGatewayRequest extends Request
{
    use CompanyGatewayFeesAndLimitsSaver;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->isAdmin();

    }

    public function rules()
    {

        $rules = [
            'gateway_key' => 'required',
            'fees_and_limits' => new ValidCompanyGatewayFeesAndLimitsRule(),
        ];
        
        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if(isset($input['config']))
            $input['config'] = encrypt($input['config']);

        if(isset($input['fees_and_limits']))
            $input['fees_and_limits'] = $this->cleanFeesAndLimits($input['fees_and_limits']);

        $this->replace($input);

    }
}

