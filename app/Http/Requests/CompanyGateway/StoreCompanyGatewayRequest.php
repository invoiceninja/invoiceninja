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

namespace App\Http\Requests\CompanyGateway;

use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidCompanyGatewayFeesAndLimitsRule;
use App\Models\Gateway;
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
            'gateway_key' => 'required|alpha_num',
            'fees_and_limits' => new ValidCompanyGatewayFeesAndLimitsRule(),
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if ($gateway = Gateway::where('key', $input['gateway_key'])->first()) {
            $default_gateway_fields = json_decode($gateway->fields);

            /*Force gateway properties */
            if (isset($input['config']) && is_object(json_decode($input['config']))) {
                foreach (json_decode($input['config']) as $key => $value) {
                    $default_gateway_fields->{$key} = $value;
                }

                $input['config'] = json_encode($default_gateway_fields);
            }

            if (isset($input['config'])) {
                $input['config'] = encrypt($input['config']);
            }

            if (isset($input['fees_and_limits'])) {
                $input['fees_and_limits'] = $this->cleanFeesAndLimits($input['fees_and_limits']);
            }
        }

        $this->replace($input);
    }
}
