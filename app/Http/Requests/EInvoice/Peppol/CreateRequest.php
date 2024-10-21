<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\EInvoice\Peppol;

use App\Models\Country;
use App\Rules\EInvoice\Peppol\SupportsReceiverIdentifier;
use App\Services\EDocument\Standards\Peppol\ReceiverIdentifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /**
         * @var \App\Models\User
         */
        $user = auth()->user();

        if (app()->isLocal()) {
            return true;
        }

        return $user->account->isPaid() &&
            $user->company()->legal_entity_id === null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'party_name' => ['required', 'string'],
            'line1' => ['required', 'string'],
            'line2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'country' => ['required', 'integer', 'exists:countries,id', new SupportsReceiverIdentifier()],
            'zip' => ['required', 'string'],
            'county' => ['required', 'string'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            message: ctrans('texts.peppol_not_paid_message'),
        );
    }

    public function country(): Country
    {
        return Country::find($this->country);
    }

    public function receiverIdentifier(): string
    {
        $identifier = new ReceiverIdentifier($this->country()->iso_3166_2);

        return $identifier->get();
    }
}
