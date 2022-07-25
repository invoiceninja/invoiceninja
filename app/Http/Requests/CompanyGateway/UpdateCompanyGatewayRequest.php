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

class UpdateCompanyGatewayRequest extends Request
{
    use CompanyGatewayFeesAndLimitsSaver;

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
        $rules = [
            'fees_and_limits' => new ValidCompanyGatewayFeesAndLimitsRule(),
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        /*Force gateway properties */
        if (isset($input['config']) && is_object(json_decode($input['config'])) && array_key_exists('gateway_key', $input)) {
            $gateway = Gateway::where('key', $input['gateway_key'])->first();
            $default_gateway_fields = json_decode($gateway->fields);

            foreach (json_decode($input['config']) as $key => $value) {
                $default_gateway_fields->{$key} = $value;
            }

            $input['config'] = json_encode($default_gateway_fields);
        }

        $input['config'] = encrypt($input['config']);

        if (isset($input['fees_and_limits'])) {
            $input['fees_and_limits'] = $this->cleanFeesAndLimits($input['fees_and_limits']);
        }

        if (isset($input['token_billing']) && $input['token_billing'] == 'disabled') {
            $input['token_billing'] = 'off';
        }

        $this->replace($input);
    }
}
