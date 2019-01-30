<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | such as the size rules. Feel free to tweak each of these messages.
    |
    */
    "accepted"         => ":attribute måste accepteras.",
    "active_url"       => ":attribute är inte en giltig webbadress.",
    "after"            => ":attribute måste vara ett datum efter den :date.",
    "alpha"            => ":attribute får endast innehålla bokstäver.",
    "alpha_dash"       => ":attribute får endast innehålla bokstäver, siffror och bindestreck.",
    "alpha_num"        => ":attribute får endast innehålla bokstäver och siffror.",
    "array"            => ":attribute måste vara en array.",
    "before"           => ":attribute måste vara ett datum innan den :date.",
    "between"          => [
        "numeric" => ":attribute måste vara en siffra mellan :min och :max.",
        "file"    => ":attribute måste vara mellan :min till :max kilobyte stor.",
        "string"  => ":attribute måste innehålla :min till :max tecken.",
        "array"   => ":attribute måste innehålla mellan :min - :max objekt.",
    ],
    "boolean"          => ":attribute måste vara sant eller falskt",
    "confirmed"        => ":attribute bekräftelsen matchar inte.",
    "date"             => ":attribute är inte ett giltigt datum.",
    "date_format"      => ":attribute matchar inte formatet :format.",
    "different"        => ":attribute och :other får inte vara lika.",
    "digits"           => ":attribute måste vara minst :digits tecken.",
    "digits_between"   => ":attribute måste vara mellan :min och :max tecken.",
    "email"            => "Fältet :attribute måste innehålla en korrekt e-postadress.",
    "exists"           => "Det valda :attribute är ogiltigt.",
    "filled"           => "Fältet :attribute är obligatoriskt.",
    "image"            => ":attribute måste vara en bild.",
    "in"               => "Det valda :attribute är ogiltigt.",
    "integer"          => ":attribute måste vara en siffra.",
    "ip"               => ":attribute måste vara en giltig IP-adress.",
    "max"              => [
        "numeric" => ":attribute får inte vara större än :max.",
        "file"    => ":attribute får max vara :max kilobyte stor.",
        "string"  => ":attribute får max innehålla :max tecken.",
        "array"   => ":attribute får inte innehålla mer än :max objekt.",
    ],
    "mimes"            => ":attribute måste vara en fil av typen: :values.",
    "min"              => [
        "numeric" => ":attribute måste vara större än :min.",
        "file"    => ":attribute måste vara minst :min kilobyte stor.",
        "string"  => ":attribute måste innehålla minst :min tecken.",
        "array"   => ":attribute måste innehålla minst :min objekt.",
    ],
    "not_in"           => "Det valda :attribute är ogiltigt.",
    "numeric"          => ":attribute måste vara en siffra.",
    "regex"            => "Formatet för :attribute är ogiltigt.",
    "required"         => "Fältet :attribute är obligatoriskt.",
    "required_if"      => "Fältet :attribute är obligatoriskt då :other är :value.",
    "required_with"    => "Fältet :attribute är obligatoriskt då :values är ifyllt.",
    "required_with_all" => "Fältet :attribute är obligatoriskt när :values är ifyllt.",
    "required_without" => "Fältet :attribute är obligatoriskt då :values ej är ifyllt.",
    "required_without_all" => "Fältet :attribute är obligatoriskt när ingen av :values är ifyllt.",
    "same"             => ":attribute och :other måste vara lika.",
    "size"             => [
        "numeric" => ":attribute måste vara :size.",
        "file"    => ":attribute får endast vara :size kilobyte stor.",
        "string"  => ":attribute måste innehålla :size tecken.",
        "array"   => ":attribute måste innehålla :size objekt.",
    ],
    "timezone"         => ":attribute måste vara en giltig tidszon.",
    "unique"           => ":attribute används redan.",
    "url"              => "Formatet :attribute är ogiltigt.",

    "positive" => "The :attribute must be greater than zero.",
    "has_credit" => "The client does not have enough credit.",
    "notmasked" => "The values are masked",
    "less_than" => 'The :attribute must be less than :value',
    "has_counter" => 'The value must contain {$counter}',
    "valid_contacts" => "All of the contacts must have either an email or name",
    "valid_invoice_items" => "The invoice exceeds the maximum amount",

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [],

];
