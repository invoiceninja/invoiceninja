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

    'accepted'             => ':attribute duhet të pranohet.',
    'active_url'           => ':attribute nuk është adresë e saktë.',
    'after'                => ':attribute duhet të jetë datë pas :date.',
    'alpha'                => ':attribute mund të përmbajë vetëm shkronja.',
    'alpha_dash'           => ':attribute mund të përmbajë vetëm shkronja, numra, dhe viza.',
    'alpha_num'            => ':attribute mund të përmbajë vetëm shkronja dhe numra.',
    'array'                => ':attribute duhet të jetë një bashkësi (array).',
    'before'               => ':attribute duhet të jetë datë para :date.',
    'between'              => [
        'numeric' => ':attribute duhet të jetë midis :min - :max.',
        'file'    => ':attribute duhet të jetë midis :min - :max kilobajtëve.',
        'string'  => ':attribute duhet të jetë midis :min - :max karaktereve.',
        'array'   => ':attribute duhet të jetë midis :min - :max elementëve.',
    ],
    'boolean'              => 'Fusha :attribute duhet të jetë e vërtetë ose e gabuar',
    'confirmed'            => ':attribute konfirmimi nuk përputhet.',
    'date'                 => ':attribute nuk është një datë e saktë.',
    'date_format'          => ':attribute nuk i përshtatet formatit :format.',
    'different'            => ':attribute dhe :other duhet të jenë të ndryshme.',
    'digits'               => ':attribute duhet të jetë :digits shifra.',
    'digits_between'       => ':attribute duhet të jetë midis :min dhe :max shifra.',
    'dimensions'           => 'The :attribute has invalid image dimensions.',
    'distinct'             => 'The :attribute field has a duplicate value.',
    'email'                => ':attribute formati është i pasaktë.',
    'exists'               => ':attribute përzgjedhur është i/e pasaktë.',
    'file'                 => 'The :attribute must be a file.',
    'filled'               => 'Fusha :attribute është e kërkuar.',
    'image'                => ':attribute duhet të jetë imazh.',
    'in'                   => ':attribute përzgjedhur është i/e pasaktë.',
    'in_array'             => 'The :attribute field does not exist in :other.',
    'integer'              => ':attribute duhet të jetë numër i plotë.',
    'ip'                   => ':attribute duhet të jetë një IP adresë e saktë.',
    'json'                 => 'The :attribute must be a valid JSON string.',
    'max'                  => [
        'numeric' => ':attribute nuk mund të jetë më tepër se :max.',
        'file'    => ':attribute nuk mund të jetë më tepër se :max kilobajtë.',
        'string'  => ':attribute nuk mund të jetë më tepër se :max karaktere.',
        'array'   => ':attribute nuk mund të ketë më tepër se :max elemente.',
    ],
    'mimes'                => ':attribute duhet të jetë një dokument i tipit: :values.',
    'mimetypes'            => ':attribute duhet të jetë një dokument i tipit: :values.',
    'min'                  => [
        'numeric' => ':attribute nuk mund të jetë më pak se :min.',
        'file'    => ':attribute nuk mund të jetë më pak se :min kilobajtë.',
        'string'  => ':attribute nuk mund të jetë më pak se :min karaktere.',
        'array'   => ':attribute nuk mund të ketë më pak se :min elemente.',
    ],
    'not_in'               => ':attribute përzgjedhur është i/e pasaktë.',
    'numeric'              => ':attribute duhet të jetë një numër.',
    'present'              => 'The :attribute field must be present.',
    'regex'                => 'Formati i :attribute është i pasaktë.',
    'required'             => 'Fusha :attribute është e kërkuar.',
    'required_if'          => 'Fusha :attribute është e kërkuar kur :other është :value.',
    'required_unless'      => 'The :attribute field is required unless :other is in :values.',
    'required_with'        => 'Fusha :attribute është e kërkuar kur :values ekziston.',
    'required_with_all'    => 'Fusha :attribute është e kërkuar kur :values ekziston.',
    'required_without'     => 'Fusha :attribute është e kërkuar kur :values nuk ekziston.',
    'required_without_all' => 'Fusha :attribute është e kërkuar kur nuk ekziston asnjë nga :values.',
    'same'                 => ':attribute dhe :other duhet të përputhen.',
    'size'                 => [
        'numeric' => ':attribute duhet të jetë :size.',
        'file'    => ':attribute duhet të jetë :size kilobajtë.',
        'string'  => ':attribute duhet të jetë :size karaktere.',
        'array'   => ':attribute duhet të ketë :size elemente.',
    ],
    'string'               => ':attribute duhet të jetë varg.',
    'timezone'             => ':attribute duhet të jetë zonë e saktë.',
    'unique'               => ':attribute është marrë tashmë.',
    'uploaded'             => 'The :attribute uploading failed.',
    'url'                  => 'Formati i :attribute është i pasaktë.',

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
