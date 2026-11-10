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

namespace App\Http\Requests\Terminal;

use App\Models\Payment;

class StoreTerminalConnectionTokenRequest extends TerminalRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->terminalAvailable()
            && $user->can('create', Payment::class)
            && $this->company_gateway->company_id == $user->companyId()
            && $this->company_gateway->supportsCardPresent();
    }

    public function rules()
    {
        return [
            'location_id' => 'required|string',
        ];
    }
}
