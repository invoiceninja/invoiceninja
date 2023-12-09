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

namespace App\Http\Requests\Nordigen;

use App\Http\Requests\Request;
use Cache;
use Log;

class ConnectNordigenBankIntegrationRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'institution_id' => 'required|string',
            'one_time_token' => 'required|string', // One Time Token
            'redirect' => 'string', // TODO: @turbo124 @todo validate, that this is a url without / at the end
        ];
    }

    // @turbo124 @todo please check for validity, when issue request from frontend
    public function prepareForValidation()
    {
        $input = $this->all();

        if (!array_key_exists('redirect', $input)) {
            $context = Cache::get($input['one_time_token']);

            if (array_key_exists('is_react', $context))
                $input["redirect"] = $context["is_react"] ? config("ninja.react_url") : config("ninja.app_url");
            else
                $input["redirect"] = config("ninja.app_url");

            Log::info($input);

            $this->replace($input);

        }
    }
}
