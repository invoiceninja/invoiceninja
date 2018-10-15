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

    "accepted"         => ":attribute må være akseptert.",
    "active_url"       => ":attribute er ikke en gyldig nettadresse.",
    "after"            => ":attribute må være en dato etter :date.",
    "alpha"            => ":attribute kan kun inneholde bokstaver.",
    "alpha_dash"       => ":attribute kan kun inneholde bokstaver, sifre, og bindestreker.",
    "alpha_num"        => ":attribute kan kun inneholde bokstaver og sifre.",
    "array"            => ":attribute må være en matrise.",
    "before"           => ":attribute må være en dato før :date.",
    "between"          => array(
        "numeric" => ":attribute må være mellom :min - :max.",
        "file"    => ":attribute må være mellom :min - :max kilobytes.",
        "string"  => ":attribute må være mellom :min - :max tegn.",
        "array"   => ":attribute må ha mellom :min - :max elementer.",
    ),
    "confirmed"        => ":attribute bekreftelsen stemmer ikke",
    "date"             => ":attribute er ikke en gyldig dato.",
    "date_format"      => ":attribute samsvarer ikke med formatet :format.",
    "different"        => ":attribute og :other må være forskjellig.",
    "digits"           => ":attribute må være :digits sifre.",
    "digits_between"   => ":attribute må være mellom :min og :max sifre.",
    "email"            => ":attribute formatet er ugyldig.",
    "exists"           => "Valgt :attribute er ugyldig.",
    "image"            => ":attribute må være et bilde.",
    "in"               => "Valgt :attribute er ugyldig.",
    "integer"          => ":attribute må være heltall.",
    "ip"               => ":attribute må være en gyldig IP-adresse.",
    "max"              => array(
        "numeric" => ":attribute kan ikke være høyere enn :max.",
        "file"    => ":attribute kan ikke være større enn :max kilobytes.",
        "string"  => ":attribute kan ikke være mer enn :max tegn.",
        "array"   => ":attribute kan ikke inneholde mer enn :max elementer.",
    ),
    "mimes"            => ":attribute må være av filtypen: :values.",
    "min"              => array(
        "numeric" => ":attribute må minimum være :min.",
        "file"    => ":attribute må minimum være :min kilobytes.",
        "string"  => ":attribute må minimum være :min tegn.",
        "array"   => ":attribute må inneholde minimum :min elementer.",
    ),
    "not_in"           => "Valgt :attribute er ugyldig.",
    "numeric"          => ":attribute må være et siffer.",
    "regex"            => ":attribute formatet er ugyldig.",
    "required"         => ":attribute er påkrevd.",
    "required_if"      => ":attribute er påkrevd når :other er :value.",
    "required_with"    => ":attribute er påkrevd når :values er valgt.",
    "required_without" => ":attribute er påkrevd når :values ikke er valgt.",
    "same"             => ":attribute og :other må samsvare.",
    "size"             => array(
        "numeric" => ":attribute må være :size.",
        "file"    => ":attribute må være :size kilobytes.",
        "string"  => ":attribute må være :size tegn.",
        "array"   => ":attribute må inneholde :size elementer.",
    ),
    "unique"           => ":attribute er allerede blitt tatt.",
    "url"              => ":attribute formatet er ugyldig.",

    "positive" => ":attribute må være mer enn null.",
    "has_credit" => "Klienten har ikke høy nok kreditt.",
    "notmasked" => "Verdiene er skjult",
    "less_than" => ':attribute må være mindre enn :value',
    "has_counter" => 'Verdien må inneholde {$counter}',

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

    'custom' => array(),

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
