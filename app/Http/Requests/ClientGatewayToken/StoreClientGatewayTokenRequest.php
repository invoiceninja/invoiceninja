<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\ClientGatewayToken;

use App\DataMapper\ClientSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Ninja\CanStoreClientsRule;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\Client;
use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class StoreClientGatewayTokenRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->isAdmin()
    }

    public function rules()
    {
        $rules = [
            'client_id' => 'required',
            'company_gateway_id' => 'required',
        ];


        return $this->globalRules($rules);
    
    }

    protected function prepareForValidation()
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
