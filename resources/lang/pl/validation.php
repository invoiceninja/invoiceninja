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

    "accepted"         => ":attribute musi być zaakceptowany.",
    "active_url"       => ":attribute nie jest poprawnym URL-em.",
    "after"            => ":attribute musi być datą za :date.",
    "alpha"            => ":attribute może zawierać tylko litery.",
    "alpha_dash"       => ":attribute może zawierać tylko litery, liczby i myślniki.",
    "alpha_num"        => ":attribute może zawierać tylko litery i liczby.",
    "array"            => ":attribute musi być tablicą.",
    "before"           => ":attribute musi być datą przed :date.",
    "between"          => array(
        "numeric" => ":attribute musi być pomiędzy :min - :max.",
        "file"    => ":attribute musi mieć rozmiar pomiędzy :min - :max kilobajtów.",
        "string"  => ":attribute musi mieć pomiędzy :min - :max znaków.",
        "array"   => ":attribute musi zawierać :min - :max pozycji.",
    ),
    "confirmed"        => ":attribute potwierdzenie nie jest zgodne.",
    "date"             => ":attribute nie jest prawidłową datą.",
    "date_format"      => ":attribute nie jest zgodne z formatem :format.",
    "different"        => ":attribute i :other muszą być różne.",
    "digits"           => ":attribute musi mieć :digits cyfr.",
    "digits_between"   => ":attribute musi być w przedziale od :min do :max cyfr.",
    "email"            => ":attribute format jest nieprawidłowy.",
    "exists"           => "Zaznaczony :attribute jest niepoprawny.",
    "image"            => ":attribute musi być zdjęciem.",
    "in"               => "Zaznaczony :attribute jest niepoprawny.",
    "integer"          => ":attribute musi być liczbą całkowitą.",
    "ip"               => ":attribute musi być poprawnym adresem IP.",
    "max"              => array(
        "numeric" => ":attribute nie może być większy niż :max.",
        "file"    => ":attribute nie może być większy niż :max kilobajtów.",
        "string"  => ":attribute nie może być dłuższy niż :max znaków.",
        "array"   => ":attribute nie może zawierać więcej niż :max pozycji.",
    ),
    "mimes"            => ":attribute musi być plikiem o typie: :values.",
    "min"              => array(
        "numeric" => ":attribute musi być przynajmniej :min.",
        "file"    => ":attribute musi mieć przynajmniej :min kilobajtów.",
        "string"  => ":attribute musi mieć przynajmniej :min znaków.",
        "array"   => ":attribute musi zawierać przynajmniej :min pozycji.",
    ),
    "not_in"           => "Zaznaczony :attribute jest niepoprawny.",
    "numeric"          => ":attribute musi być cyfrą.",
    "regex"            => ":attribute format jest niepoprawny.",
    "required"         => ":attribute pole jest wymagane.",
    "required_if"      => ":attribute pole jest wymagane jeśli :other ma :value.",
    "required_with"    => ":attribute pole jest wymagane kiedy :values jest obecne.",
    "required_without" => ":attribute pole jest wymagane kiedy :values nie występuje.",
    "same"             => ":attribute i :other muszą być takie same.",
    "size"             => array(
        "numeric" => ":attribute musi mieć :size.",
        "file"    => ":attribute musi mieć :size kilobajtów.",
        "string"  => ":attribute musi mieć :size znaków.",
        "array"   => ":attribute musi zawierać :size pozycji.",
    ),
    "unique"           => ":attribute już istnieje.",
    "url"              => ":attribute format jest nieprawidłowy.",

    "positive" => ":attribute musi być większe niż zero.",
    "has_credit" => "Klient ma niewystarczająco kredytu.",
    "notmasked" => "Wartości są maskowane",
    "less_than" => ":attribute musi być mniejsze od :value",
    "has_counter" => "Wartość musi zawierać {\$counter}",
    "valid_contacts" => "Kontakt musi posiadać e-mail lub nazwę",
    "valid_invoice_items" => "Faktura przekracza maksymalną kwotę",

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