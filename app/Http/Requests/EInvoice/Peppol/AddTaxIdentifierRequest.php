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
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Auth\Access\AuthorizationException;
use App\Rules\EInvoice\Peppol\SupportsReceiverIdentifier;
use App\Services\EDocument\Standards\Peppol\ReceiverIdentifier;

class AddTaxIdentifierRequest extends FormRequest
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

        return $user->account->isPaid() && $user->isAdmin() && $user->company()->legal_entity_id != null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country' => ['required', 'bail', Rule::in(array_keys($this->vat_regex_patterns))],
            'vat_number' => [
               'required',
               'string',
               'bail',
               function ($attribute, $value, $fail) {
                   if ($this->country && isset($this->vat_regex_patterns[$this->country])) {
                       if (!preg_match($this->vat_regex_patterns[$this->country], $value)) {
                           $fail(ctrans('texts.invalid_vat_number'));
                       }
                   }
               },
            ]
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if(isset($input['country'])) {
            $country = $this->country();
            $input['country'] = $country->iso_3166_2;
        }

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
