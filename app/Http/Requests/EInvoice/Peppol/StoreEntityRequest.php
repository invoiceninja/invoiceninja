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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreEntityRequest extends FormRequest
{

    private array $vat_regex_patterns = [
        'DE' => '/^DE\d{9}$/',
        'AT' => '/^ATU\d{8}$/',
        'BE' => '/^BE0\d{9}$/',
        'BG' => '/^BG\d{9,10}$/',
        'CY' => '/^CY\d{8}L$/',
        'HR' => '/^HR\d{11}$/',
        'DK' => '/^DK\d{8}$/',
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/',
        'EE' => '/^EE\d{9}$/',
        'FI' => '/^FI\d{8}$/',
        'FR' => '/^FR\d{2}\d{9}$/',
        'EL' => '/^EL\d{9}$/',
        'HU' => '/^HU\d{8}$/',
        'IE' => '/^IE\d{7}[A-Z]{1,2}$/',
        'IT' => '/^IT\d{11}$/',
        'LV' => '/^LV\d{11}$/',
        'LT' => '/^LT(\d{9}|\d{12})$/',
        'LU' => '/^LU\d{8}$/',
        'MT' => '/^MT\d{8}$/',
        'NL' => '/^NL\d{9}B\d{2}$/',
        'PL' => '/^PL\d{10}$/',
        'PT' => '/^PT\d{9}$/',
        'CZ' => '/^CZ\d{8,10}$/',
        'RO' => '/^RO\d{2,10}$/',
        'SK' => '/^SK\d{10}$/',
        'SI' => '/^SI\d{8}$/',
        'SE' => '/^SE\d{12}$/',
    ];

    public function authorize(): bool
    {
        /**
         * @var \App\Models\User
         */
        $user = auth()->user();

        if (app()->isLocal()) {
            return true;
        }

        return $user->account->isPaid() && $user->isAdmin() && 
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
            'country' => ['required', 'bail', Rule::in(array_keys($this->vat_regex_patterns))],
            'zip' => ['required', 'string'],
            'county' => ['required', 'string'],
            'acts_as_receiver' => ['required', 'bool'],
            'acts_as_sender' => ['required', 'bool'],
            'tenant_id' => ['required'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            message: ctrans('texts.peppol_not_paid_message'),
        );
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if(isset($input['country'])) {
            $country = $this->country();
            $input['country'] = $country->iso_3166_2;
        }

        $input['acts_as_receiver'] = $input['acts_as_receiver'] ?? true;
        $input['acts_as_sender'] = $input['acts_as_sender'] ?? true;

        $this->replace($input);

    }

    public function country(): Country
    {
        
        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        return $countries->first(function ($c){
            return $this->country == $c->id;
        });
    }

}
