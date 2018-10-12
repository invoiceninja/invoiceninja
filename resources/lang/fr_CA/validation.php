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

    "accepted"             => "Le champ :attribute doit être accepté.",
    "active_url"           => "Le champ :attribute n'est pas une URL valide.",
    "after"                => "Le champ :attribute doit être une date postérieure au :date.",
    "alpha"                => "Le champ :attribute doit seulement contenir des lettres.",
    "alpha_dash"           => "Le champ :attribute doit seulement contenir des lettres, des chiffres et des tirets.",
    "alpha_num"            => "Le champ :attribute doit seulement contenir des chiffres et des lettres.",
    "array"                => "Le champ :attribute doit être un tableau.",
    "before"               => "Le champ :attribute doit être une date antérieure au :date.",
    "between"              => array(
        "numeric" => "La valeur de :attribute doit être comprise entre :min et :max.",
        "file"    => "Le fichier :attribute doit avoir une taille entre :min et :max kilobytes.",
        "string"  => "Le texte :attribute doit avoir entre :min et :max caractères.",
        "array"   => "Le champ :attribute doit avoir entre :min et :max éléments.",
    ),
    "confirmed"            => "Le champ de confirmation :attribute ne correspond pas.",
    "date"                 => "Le champ :attribute n'est pas une date valide.",
    "date_format"          => "Le champ :attribute ne correspond pas au format :format.",
    "different"            => "Les champs :attribute et :other doivent être différents.",
    "digits"               => "Le champ :attribute doit avoir :digits chiffres.",
    "digits_between"       => "Le champ :attribute doit avoir entre :min and :max chiffres.",
    "email"                => "Le champ :attribute doit être une adresse email valide.",
    "exists"               => "Le champ :attribute sélectionné est invalide.",
    "image"                => "Le champ :attribute doit être une image.",
    "in"                   => "Le champ :attribute est invalide.",
    "integer"              => "Le champ :attribute doit être un entier.",
    "ip"                   => "Le champ :attribute doit être une adresse IP valide.",
    "max"                  => array(
        "numeric" => "La valeur de :attribute ne peut être supérieure à :max.",
        "file"    => "Le fichier :attribute ne peut être plus gros que :max kilobytes.",
        "string"  => "Le texte de :attribute ne peut contenir plus de :max caractères.",
        "array"   => "Le champ :attribute ne peut avoir plus de :max éléments.",
    ),
    "mimes"                => "Le champ :attribute doit être un fichier de type : :values.",
    "min"                  => array(
        "numeric" => "La valeur de :attribute doit être supérieure à :min.",
        "file"    => "Le fichier :attribute doit être plus que gros que :min kilobytes.",
        "string"  => "Le texte :attribute doit contenir au moins :min caractères.",
        "array"   => "Le champ :attribute doit avoir au moins :min éléments.",
    ),
    "not_in"               => "Le champ :attribute sélectionné n'est pas valide.",
    "numeric"              => "Le champ :attribute doit contenir un nombre.",
    "regex"                => "Le format du champ :attribute est invalide.",
    "required"             => "Le champ :attribute est obligatoire.",
    "required_if"          => "Le champ :attribute est obligatoire quand la valeur de :other est :value.",
    "required_with"        => "Le champ :attribute est obligatoire quand :values est présent.",
    "required_with_all"    => "Le champ :attribute est obligatoire quand :values est présent.",
    "required_without"     => "Le champ :attribute est obligatoire quand :values n'est pas présent.",
    "required_without_all" => "Le champ :attribute est requis quand aucun de :values n'est présent.",
    "same"                 => "Les champs :attribute et :other doivent être identiques.",
    "size"                 => array(
        "numeric" => "La valeur de :attribute doit être :size.",
        "file"    => "La taille du fichier de :attribute doit être de :size kilobytes.",
        "string"  => "Le texte de :attribute doit contenir :size caractères.",
        "array"   => "Le champ :attribute doit contenir :size éléments.",
    ),
    "unique"               => "La valeur du champ :attribute est déjà utilisée.",
    "url"                  => "Le format de l'URL de :attribute n'est pas valide.",

    "positive" => ":attribute doit être supérieur à zero.",
    "has_credit" => "Le client n'a pas un crédit suffisant.",
    "notmasked" => "Les valeurs sont masquées",
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

    'attributes' => array(
        "name" => "Nom",
        "username" => "Pseudo",
        "email" => "Courriel",
        "first_name" => "Prénom",
        "last_name" => "Nom",
        "password" => "Mot de passe",
        "password_confirmation" => "Confirmation du mot de passe",
        "city" => "Ville",
        "country" => "Pays",
        "address" => "Adresse",
        "phone" => "Téléphone",
        "mobile" => "Mobile",
        "age" => "Âge",
        "sex" => "Sexe",
        "gender" => "Genre",
        "day" => "Jour",
        "month" => "Mois",
        "year" => "Année",
        "hour" => "Heure",
        "minute" => "Minute",
        "second" => "Seconde",
        "title" => "Titre",
        "content" => "Contenu",
        "description" => "Description",
        "excerpt" => "Extrait",
        "date" => "Date",
        "time" => "Heure",
        "available" => "Disponible",
        "size" => "Taille",
    ),

);
