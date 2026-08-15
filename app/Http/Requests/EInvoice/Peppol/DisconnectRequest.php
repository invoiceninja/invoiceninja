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

namespace App\Http\Requests\EInvoice\Peppol;

use App\Models\Country;
use App\Rules\EInvoice\Peppol\SupportsReceiverIdentifier;
use App\Rules\EInvoice\PeppolLegalEntityState;
use App\Services\EDocument\Standards\Peppol\ReceiverIdentifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisconnectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /**
         * @var \App\Models\User
         */
        $user = auth()->user();

        if (config('ninja.app_env') == 'local') {
            return true;
        }

        return $user->account->isPaid() && $user->isAdmin()
           && $user->company()->legal_entity_id !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $company = auth()->user()->company();

        return [
            'company_key' => ['required', Rule::in([(string) $company->company_key])],
            'legal_entity_id' => [
                'prohibited',
                PeppolLegalEntityState::present($company),
            ],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            message: ctrans('texts.peppol_not_paid_message'),
        );
    }
}
