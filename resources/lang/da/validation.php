<?php

return array(

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

    "accepted"         => ":attribute skal accepteres.",
    "active_url"       => ":attribute er ikke en valid URL.",
    "after"            => ":attribute skal være en dato efter :date.",
    "alpha"            => ":attribute må kun bestå af bogstaver.",
    "alpha_dash"       => ":attribute må kun bestå af bogstaver, tal og bindestreger.",
    "alpha_num"        => ":attribute må kun bestå af bogstaver og tal.",
    "array"            => ":attribute skal være et array.",
    "before"           => ":attribute skal være en dato før :date.",
    "between"          => array(
        "numeric" => ":attribute skal være imellem :min - :max.",
        "file"    => ":attribute skal være imellem :min - :max kilobytes.",
        "string"  => ":attribute skal være imellem :min - :max tegn.",
        "array"   => ":attribute skal indeholde mellem :min - :max elementer.",
    ),
    "boolean"          => ":attribute skal være sandt eller falsk",
    "confirmed"        => ":attribute er ikke det samme som bekræftelsesfeltet.",
    "date"             => ":attribute er ikke en gyldig dato.",
    "date_format"      => ":attribute matcher ikke formatet :format.",
    "different"        => ":attribute og :other skal være forskellige.",
    "digits"           => ":attribute skal have :digits cifre.",
    "digits_between"   => ":attribute skal have mellem :min og :max cifre.",
    "email"            => ":attribute skal være en gyldig e-mailadresse.",
    "exists"           => "Det valgte :attribute er ugyldig.",
    "image"            => ":attribute skal være et billede.",
    "in"               => "Det valgte :attribute er ugyldig.",
    "integer"          => ":attribute skal være et heltal.",
    "ip"               => ":attribute skal være en gyldig IP adresse.",
    "max"              => array(
        "numeric" => ":attribute skal være højest :max.",
        "file"    => ":attribute skal være højest :max kilobytes.",
        "string"  => ":attribute skal være højest :max tegn.",
        "array"   => ":attribute må ikke indeholde mere end :max elementer.",
    ),
    "mimes"            => ":attribute skal være en fil af typen: :values.",
    "min"              => array(
        "numeric" => ":attribute skal være mindst :min.",
        "file"    => ":attribute skal være mindst :min kilobytes.",
        "string"  => ":attribute skal være mindst :min tegn.",
        "array"   => ":attribute skal indeholde mindst :min elementer.",
    ),
    "not_in"           => "Den valgte :attribute er ugyldig.",
    "numeric"          => ":attribute skal være et tal.",
    "regex"            => ":attribute formatet er ugyldigt.",
    "required"         => ":attribute skal udfyldes.",
    "required_if"      => ":attribute skal udfyldes når :other er :value.",
    "required_with"    => ":attribute skal udfyldes når :values er udfyldt.",
    "required_with_all" => ":attribute skal udfyldes når :values er udfyldt.",
    "required_without" => ":attribute skal udfyldes når :values ikke er udfyldt.",
    "required_without_all" => ":attribute skal udfyldes når ingen af :values er udfyldt.",
    "same"             => ":attribute og :other skal være ens.",
    "size"             => array(
        "numeric" => ":attribute skal være :size.",
        "file"    => ":attribute skal være :size kilobytes.",
        "string"  => ":attribute skal være :size tegn lang.",
        "array"   => ":attribute skal indeholde :size elementer.",
    ),
    "timezone"         => "The :attribute must be a valid zone.",
    "unique"           => ":attribute er allerede taget.",
    "url"              => ":attribute formatet er ugyldigt.",

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

    'custom' => array(
        'attribute-name' => array(
            'rule-name' => 'custom-message',
        ),
    ),

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

    'attributes' => array(),

);
