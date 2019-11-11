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

class StoreCompanyGatewayRequest extends Request
{
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

        $this->sanitize();

        $rules = [
            'gateway_key' => 'required',
            'fees_and_limits' => new ValidCompanyGatewayFeesAndLimitsRule(),
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


        // $input['min_limit'] = isset($input['fees_and_limits']['min_limit']) ? $input['fees_and_limits']['min_limit'] : null;
        // $input['max_limit'] = isset($input['fees_and_limits']['max_limit']) ? $input['fees_and_limits']['max_limit'] : null;
        // $input['fee_amount'] = isset($input['fees_and_limits']['fee_amount']) ? $input['fees_and_limits']['fee_amount'] : null;
        // $input['fee_percent'] = isset($input['fees_and_limits']['fee_percent']) ? $input['fees_and_limits']['fee_percent'] : null;
        // $input['fee_tax_name1'] = isset($input['fees_and_limits']['fee_tax_name1']) ? $input['fees_and_limits']['fee_tax_name1'] : '';
        // $input['fee_tax_name2'] = isset($input['fees_and_limits']['fee_tax_name2']) ? $input['fees_and_limits']['fee_tax_name2'] : '';
        // $input['fee_tax_name3'] = isset($input['fees_and_limits']['fee_tax_name3']) ? $input['fees_and_limits']['fee_tax_name3'] : '';
        // $input['fee_tax_rate1'] = isset($input['fees_and_limits']['fee_tax_rate1']) ? $input['fees_and_limits']['fee_tax_rate1'] : 0;
        // $input['fee_tax_rate2'] = isset($input['fees_and_limits']['fee_tax_rate2']) ? $input['fees_and_limits']['fee_tax_rate2'] : 0;
        // $input['fee_tax_rate3'] = isset($input['fees_and_limits']['fee_tax_rate3']) ? $input['fees_and_limits']['fee_tax_rate3'] : 0;
        // $input['fee_cap'] = isset($input['fees_and_limits']['fee_cap']) ? $input['fees_and_limits']['fee_cap'] : null;
        // $input['adjust_fee_percent'] = isset($input['fees_and_limits']['adjust_fee_percent']) ? $input['fees_and_limits']['adjust_fee_percent'] : 0;
