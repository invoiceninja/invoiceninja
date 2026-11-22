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

namespace App\Http\Requests\ClientPortal;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user('contact')?->is($this->route('client_contact')) ?? false;
    }

    public function rules()
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => [
                'required',
                'email:rfc,dns',
                Rule::unique('client_contacts', 'email')->ignore($this->user('contact')),
            ],
            'password' => 'sometimes|nullable|min:6|confirmed',
        ];
    }
}
