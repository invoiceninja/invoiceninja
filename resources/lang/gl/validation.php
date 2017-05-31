<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | such as the size rules. FeO free to tweak each of these messages.
    |
    */

    "accepted"         => ":attribute debe ser aceptado.",
    "active_url"       => ":attribute non é unha URL válida.",
    "after"            => ":attribute debe ser unha data posterior a :date.",
    "alpha"            => ":attribute debe conter só letras.",
    "alpha_dash"       => ":attribute debe conter só letras, números e guións.",
    "alpha_num"        => ":attribute debe conter só letras e números.",
    "array"            => ":attribute debe ser un conxunto.",
    "before"           => ":attribute debe ser unha data anterior a :date.",
    "between"          => array(
        "numeric" => ":attribute ten que estar entre :min - :max.",
        "file"    => ":attribute debe pesar entre :min - :max kilobytes.",
        "string"  => ":attribute ten que conter entre :min - :max caracteres.",
        "array"   => ":attribute ten que conter entre :min - :max ítems.",
    ),
    "confirmed"        => "A confirmación de :attribute non coincide.",
    "date"             => ":attribute non é unha data válida.",
    "date_format"      => ":attribute non corresponde co formato :format.",
    "different"        => ":attribute e :other deben ser diferentes.",
    "digits"           => ":attribute debe conter :digits díxitos.",
    "digits_between"   => ":attribute debe conter entre :min e :max díxitos.",
    "email"            => ":attribute non é un correo válido",
    "exists"           => ":attribute é inválido.",
    "image"            => ":attribute debe ser unha imaxe.",
    "in"               => ":attribute non é válido.",
    "integer"          => ":attribute debe ser un número enteiro.",
    "ip"               => ":attribute debe ser un enderezo IP válido.",
    "max"              => array(
        "numeric" => ":attribute non pode ser maior a :max.",
        "file"    => ":attribute non pode ser maior que :max kilobytes.",
        "string"  => ":attribute non pode ser maior que :max caracteres.",
        "array"   => ":attribute non pode ter más de :max elementos.",
    ),
    "mimes"            => ":attribute debe ser un arquivo co formato: :values.",
    "min"              => array(
        "numeric" => "O tamaño de :attribute debe ser de polo menos :min.",
        "file"    => "O tamaño de :attribute debe ser de polo menos :min kilobytes.",
        "string"  => ":attribute debe conter polo menos :min caracteres.",
        "array"   => ":attribute debe conter polo menos :min elementos.",
    ),
    "not_in"           => ":attribute non é válido.",
    "numeric"          => ":attribute debe ser un número.",
    "regex"            => "O formato de :attribute non é válido.",
    "required"         => "O campo :attribute é obrigatorio.",
    "required_if"      => "O campo :attribute é obrigatorio cando :other é :value.",
    "required_with"    => "O campo :attribute é obrigatorio cando :values está presente.",
    "required_without" => "O campo :attribute é obrigatorio cando :values non está presente.",
    "same"             => ":attribute e :other deben coincidir.",
    "size"             => array(
        "numeric" => "O tamaño de :attribute debe ser :size.",
        "file"    => "O tamaño de :attribute debe ser :size kilobytes.",
        "string"  => ":attribute debe conter :size caracteres.",
        "array"   => ":attribute debe conter :size elementos.",
    ),
    "unique"     => ":attribute xa foi registrado.",
    "url"        => "O formato :attribute non é válido.",
    "positive"   => ":attribute debe ser maior ca cero.",
    "has_credit" => "O cliente non ten crédito suficiente.",
    "notmasked" => "Os valores estan enmascarados",
    "less_than" => 'O valor :attribute debe ser menor que :value',
    "has_counter" => 'O valor debe conter {$counter}',
    "valid_contacts" => "Todos os contactos deben ter un correo electrónico ou nome",
    "valid_invoice_items" => "A factura excedeu a cantidade máxima",

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
