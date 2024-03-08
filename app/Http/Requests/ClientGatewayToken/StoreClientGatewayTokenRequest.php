<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ClientGatewayToken;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Utils\Traits\MakesHash;

class StoreClientGatewayTokenRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        //ensure client is present
        $rules = [
            'client_id' => 'required|exists:clients,id,company_id,'.auth()->user()->company()->id,
            'company_gateway_id' => 'required',
            'gateway_type_id' => 'required|integer',
            'meta' => 'required',
        ];

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }

    public function messages()
    {
        return [
        ];
    }
}
