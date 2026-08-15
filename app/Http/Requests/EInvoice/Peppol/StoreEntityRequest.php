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
use App\Rules\EInvoice\PeppolLegalEntityState;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;

class StoreEntityRequest extends FormRequest
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
           && $user->company()->legal_entity_id === null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $company = auth()->user()->company();
        $isSG = $this->input('country') == '702' || $this->country_id == 702;
        $isFRBusiness = $this->input('country') === 'FR' && $this->input('classification') !== 'individual';

        $id_number_rules = [
            Rule::requiredIf(fn() => $this->input('classification') === 'individual' || $isSG),
            'nullable',
        ];
        $vat_number_rules = [
            'bail',
            Rule::requiredIf(fn() => $this->input('classification') !== 'individual' && !$isSG),
        ];

        if ($isFRBusiness) {
            $id_number_rules[] = function ($attribute, $value, $fail) {
                if ($value === null || $value === '') {
                    return;
                }

                $siret = preg_replace("/[^0-9]/", "", (string) $value);

                if (! (new StorecoveIdentifierValidator())->validFormat('FR:SIRET', $siret)) {
                    $fail('When supplied, id_number must be a valid 14-digit SIRET (FR:SIRET). The SIREN is derived from the VAT number.');
                }
            };

            $vat_number_rules[] = function ($attribute, $value, $fail): void {
                $vat = preg_replace('/[^0-9]/', '', (string) $value);
                $siren = strlen($vat) >= 9 ? substr($vat, -9) : '';

                if (! (new StorecoveIdentifierValidator())->validFormat('FR:SIRENE', $siren)) {
                    $fail('vat_number must contain a valid 9-digit SIREN for French CTC registration.');
                }
            };
        }

        return [
            'party_name' => ['required', 'string'],
            'line1' => ['required', 'string'],
            'line2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'country' => ['required', 'bail', Rule::in(array_keys(AddTaxIdentifierRequest::$vat_regex_patterns))],
            'zip' => ['required', 'string'],
            'county' => ['required', 'string'],
            'acts_as_receiver' => ['required', 'bool'],
            'acts_as_sender' => ['required', 'bool'],
            'tenant_id' => ['sometimes', 'nullable', 'string'],
            'classification' => ['required', 'in:business,individual'],
            'vat_number' => $vat_number_rules,
            'id_number' => $id_number_rules,
            'c5_signer_name' => [Rule::requiredIf($isSG), 'nullable', 'string', 'min:2', 'max:64'],
            'c5_signer_email' => [Rule::requiredIf($isSG), 'nullable', 'email'],
            'legal_entity_id' => [
                'prohibited',
                PeppolLegalEntityState::absent($company),
            ],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            message: ctrans('texts.peppol_not_paid_message'),
        );
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (isset($input['country'])) {
            $country = $this->country();
            $input['country'] = $country->iso_3166_2;
            $input['country_id'] = $country->id;
        }

        $input['acts_as_receiver'] ??= true;
        $input['acts_as_sender'] ??= true;

        $this->replace($input);
    }

    public function country(): Country
    {
        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        return $countries->first(function ($c) {
            return $this->country == $c->id;
        });
    }
}
