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

    'accepted'             => ':attribute ha de ser acceptat.',
    'active_url'           => ':attribute no és un URL vàlid.',
    'after'                => ':attribute ha de ser una data posterior a :date.',
    'alpha'                => ':attribute només pot contenir lletres.',
    'alpha_dash'           => ':attribute només por contenir lletres, números i guions.',
    'alpha_num'            => ':attribute només pot contenir lletres i números.',
    'array'                => ':attribute ha de ser un conjunt.',
    'before'               => ':attribute ha de ser una data anterior a :date.',
    'between'              => [
        'numeric' => ":attribute ha d'estar entre :min - :max.",
        'file'    => ':attribute ha de pesar entre :min - :max kilobytes.',
        'string'  => ':attribute ha de tenir entre :min - :max caràcters.',
        'array'   => ':attribute ha de tenir entre :min - :max ítems.',
    ],
    'boolean'              => 'El camp :attribute ha de ser veritat o fals',
    'confirmed'            => 'La confirmació de :attribute no coincideix.',
    'date'                 => ':attribute no és una data vàlida.',
    'date_format'          => ':attribute no correspon al format :format.',
    'different'            => ':attribute i :other han de ser diferents.',
    'digits'               => ':attribute ha de tenir :digits digits.',
    'digits_between'       => ':attribute ha de tenir entre :min i :max digits.',
    'dimensions'           => 'The :attribute has invalid image dimensions.',
    'distinct'             => 'The :attribute field has a duplicate value.',
    'email'                => ':attribute no és un e-mail vàlid',
    'exists'               => ':attribute és invàlid.',
    'filled'               => 'El camp :attribute és obligatori.',
    'image'                => ':attribute ha de ser una imatge.',
    'in'                   => ':attribute és invàlid',
    'in_array'             => 'The :attribute field does not exist in :other.',
    'integer'              => ':attribute ha de ser un nombre enter.',
    'ip'                   => ':attribute ha de ser una adreça IP vàlida.',
    'json'                 => 'El camp :attribute ha de contenir una cadena JSON vàlida.',
    'max'                  => [
        'numeric' => ':attribute no ha de ser major a :max.',
        'file'    => ':attribute no ha de ser més gran que :max kilobytes.',
        'string'  => ':attribute no ha de ser més gran que :max characters.',
        'array'   => ':attribute no ha de tenir més de :max ítems.',
    ],
    'mimes'                => ':attribute ha de ser un arxiu amb format: :values.',
    'min'                  => [
        'numeric' => "El tamany de :attribute ha de ser d'almenys :min.",
        'file'    => "El tamany de :attribute ha de ser d'almenys :min kilobytes.",
        'string'  => ':attribute ha de contenir almenys :min caràcters.',
        'array'   => ':attribute ha de tenir almenys :min ítems.',
    ],
    'not_in'               => ':attribute és invàlid.',
    'numeric'              => ':attribute ha de ser numèric.',
    'present'              => 'The :attribute field must be present.',
    'regex'                => 'El format de :attribute és invàlid.',
    'required'             => 'El camp :attribute és obligatori.',
    'required_if'          => 'El camp :attribute és obligatori quan :other és :value.',
    'required_unless'      => 'The :attribute field is required unless :other is in :values.',
    'required_with'        => 'El camp :attribute és obligatori quan :values és present.',
    'required_with_all'    => 'El camp :attribute és obligatori quan :values és present.',
    'required_without'     => 'El camp :attribute és obligatori quan :values no és present.',
    'required_without_all' => 'El camp :attribute és obligatori quan cap dels :values estan presents.',
    'same'                 => ':attribute i :other han de coincidir.',
    'size'                 => [
        'numeric' => 'El tamany de :attribute ha de ser :size.',
        'file'    => 'El tamany de :attribute ha de ser :size kilobytes.',
        'string'  => ':attribute ha de contenir :size caràcters.',
        'array'   => ':attribute ha de contenir :size ítems.',
    ],
    'string'               => 'El camp :attribute ha de ser una cadena de caràcters.',
    'timezone'             => 'El camp :attribute ha de ser una zona vàlida.',
    'unique'               => ':attribute ja ha estat registrat.',
    'url'                  => 'El format :attribute és invàlid.',

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

    'custom'               => [
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

    'attributes'           => [
        //
    ],

];
