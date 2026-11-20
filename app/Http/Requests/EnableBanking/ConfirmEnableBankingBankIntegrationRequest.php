<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\EnableBanking;

use App\Http\Requests\Request;
use App\Libraries\MultiDB;
use App\Models\Company;
use Cache;

class ConfirmEnableBankingBankIntegrationRequest extends Request
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
            'code' => 'required|string',
            'state' => 'sometimes|string',
        ];
    }

    /**
     * @return array{
     *   user_id: int,
     *   company_key: string,
     *   context: string,
     *   lang: string,
     *   redirect: string,
     *   aspsp_name?: string,
     *   aspsp_country?: string,
     *   state?: string
     * }
     */
    public function getTokenContent(): ?array
    {
        // On the OAuth return the token arrives as the "state" query parameter;
        // fall back to the "token" input for direct calls. Avoid assigning a
        // dynamic property on the request (deprecated as of PHP 8.2).
        $token = $this->state ?: $this->token;

        return Cache::get($token);
    }

    public function getCompany(): Company
    {
        $key = $this->getTokenContent()['company_key'];

        MultiDB::findAndSetDbByCompanyKey($key);

        return Company::where('company_key', $key)->firstOrFail();
    }
}