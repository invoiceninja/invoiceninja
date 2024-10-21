<?php

namespace App\Rules\EInvoice\Peppol;

use App\Models\Country;
use App\Services\EDocument\Standards\Peppol\ReceiverIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupportsReceiverIdentifier implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $country = Country::find($value);

        if ($country === null) {
            $fail(ctrans('texts.peppol_country_not_supported'));
        }

        $checker = new ReceiverIdentifier($country->iso_3166_2);

        if ($checker->get() === null) {
            $fail(ctrans('texts.peppol_country_not_supported'));
        }
    }
}
