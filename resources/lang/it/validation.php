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
    "accepted"         => ":attribute deve essere accettato.",
    "active_url"       => ":attribute non è un URL valido.",
    "after"            => ":attribute deve essere una data successiva al :date.",
    "alpha"            => ":attribute può contenere solo lettere.",
    "alpha_dash"       => ":attribute può contenere solo lettere, numeri e trattini.",
    "alpha_num"        => ":attribute può contenere solo lettere e numeri.",
    "array"            => ":attribute deve essere un array.",
    "before"           => ":attribute deve essere una data precedente al :date.",
    "between"          => array(
        "numeric" => ":attribute deve trovarsi tra :min - :max.",
        "file"    => ":attribute deve trovarsi tra :min - :max kilobytes.",
        "string"  => ":attribute deve trovarsi tra :min - :max caratteri.",
        "array"   => ":attribute deve avere tra :min - :max elementi.",
    ),
    "confirmed"        => "Il campo di conferma per :attribute non coincide.",
    "date"             => ":attribute non è una data valida.",
    "date_format"      => ":attribute non coincide con il formato :format.",
    "different"        => ":attribute e :other devono essere differenti.",
    "digits"           => ":attribute deve essere di :digits cifre.",
    "digits_between"   => ":attribute deve essere tra :min e :max cifre.",
    "email"            => ":attribute non è valido.",
    "exists"           => ":attribute selezionato/a non è valido.",
    "image"            => ":attribute deve essere un'immagine.",
    "in"               => ":attribute selezionato non è valido.",
    "integer"          => ":attribute deve essere intero.",
    "ip"               => ":attribute deve essere un indirizzo IP valido.",
    "max"              => array(
        "numeric" => ":attribute deve essere minore di :max.",
        "file"    => ":attribute non deve essere più grande di :max kilobytes.",
        "string"  => ":attribute non può contenere più di :max caratteri.",
        "array"   => ":attribute non può avere più di :max elementi.",
    ),
    "mimes"            => ":attribute deve essere del tipo: :values.",
    "min"              => array(
        "numeric" => ":attribute deve valere almeno :min.",
        "file"    => ":attribute deve essere più grande di :min kilobytes.",
        "string"  => ":attribute deve contenere almeno :min caratteri.",
        "array"   => ":attribute deve avere almeno :min elementi.",
    ),
    "not_in"           => "Il valore selezionato per :attribute non è valido.",
    "numeric"          => ":attribute deve essere un numero.",
    "regex"            => "Il formato del campo :attribute non è valido.",
    "required"         => ":attribute è richiesto.",
    "required_if"      => "Il campo :attribute è richiesto quando :other è :value.",
    "required_with"    => "Il campo :attribute è richiesto quando :values è presente.",
    "required_with_all" => "The :attribute field is required when :values is present.",
    "required_without" => "Il campo :attribute è richiesto quando :values non è presente.",
    "required_without_all" => "The :attribute field is required when none of :values are present.",
    "same"             => ":attribute e :other devono coincidere.",
    "size"             => array(
        "numeric" => ":attribute deve valere :size.",
        "file"    => ":attribute deve essere grande :size kilobytes.",
        "string"  => ":attribute deve contenere :size caratteri.",
        "array"   => ":attribute deve contenere :size elementi.",
    ),
    "unique"           => ":attribute è stato già utilizzato.",
    "url"              => ":attribute deve essere un URL.",

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
