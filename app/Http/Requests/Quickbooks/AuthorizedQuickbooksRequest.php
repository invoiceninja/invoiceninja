<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Quickbooks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class AuthorizedQuickbooksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return is_array($this->getTokenContent());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string',
            'state' => 'required|string',
            'realmId' => 'required|string',
        ];
    }

    /**
     * Resolve one-time token instance.
     *
     * @return mixed
     */
    public function getTokenContent()
    {
        $state_data = Cache::get($this->state);

        // Handle reconnect format: ['token' => $token, 'expected_realm_id' => ..., 'is_reconnect' => true]
        // vs regular format: just the token string
        $token = is_array($state_data) ? $state_data['token'] : $state_data;

        $data = Cache::get($token);

        return $data;
    }

    public function getContact()
    {
        return User::findOrFail($this->getTokenContent()['user_id']);
    }

    public function getCompany()
    {
        return Company::where('company_key', $this->getTokenContent()['company_key'])->firstOrFail();
    }
}
