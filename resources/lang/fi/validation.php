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

    'accepted'             => 'Kenttä :attribute tulee hyväksyä.',
    'active_url'           => 'Kentän :attribute tulee olla validi URL-osoite.',
    'after'                => 'Kentän :attribute päiväyksen tulee olla :date jälkeen.',
    'after_or_equal'       => 'The :attribute must be a date after or equal to :date.',
    'alpha'                => 'Kenttä :attribute voi sisältää vain kirjaimia.',
    'alpha_dash'           => 'Kenttä :attribute voi sisältää vain kirjaimia, numeroita ja viivoja.',
    'alpha_num'            => 'Kenttä :attribute voi sisältää vain kirjaimia ja numeroita.',
    'array'                => 'The :attribute must be an array.',
    'before'               => 'Kentän :attribute päiväyksen tulee olla ennen :date.',
    'before_or_equal'      => 'The :attribute must be a date before or equal to :date.',
    'between'              => [
        'numeric' => 'Kentän :attribute tulee olla välillä :min - :max.',
        'file'    => 'Tiedoston :attribute tulee olla :min - :max kilobittiä.',
        'string'  => 'Kentän :attribute tulee olla :min - :max merkkiä pitkä.',
        'array'   => 'Kentän :attribute tulee sisältää välillä :min - :max arvoa.',
    ],
    'boolean'              => 'Kentän :attribute arvon tulee olla tosi tai epätosi.',
    'confirmed'            => 'Kentän :attribute vahvistus ei täsmää.',
    'date'                 => 'Kentän :attribute arvo ei ole kelvollinen päivämäärä.',
    'date_format'          => 'Kentän :attribute arvo ei vastaa muotoa :format.',
    'different'            => 'Kenttien :attribute ja :other tulee olla eriarvoisia.',
    'digits'               => 'Kentän :attribute arvon on oltava :digits numeroa.',
    'digits_between'       => 'Kentän :attribute arvon tulee olla :min - :max numeroa.',
    'dimensions'           => 'The :attribute has invalid image dimensions.',
    'distinct'             => 'Kentän :attribute arvo ei ole uniikki.',
    'email'                => 'Kentän :attribute arvo ei ole validi sähköpostiosoite.',
    'exists'               => 'The selected :attribute is invalid.',
    'file'                 => 'Kentän :attribute arvon tulee olla tiedosto.',
    'filled'               => 'Kenttä :attribute on pakollinen.',
    'image'                => 'Kentän :attribute arvon tulee olla kuva.',
    'in'                   => 'Kentän :attribute arvo on virheellinen.',
    'in_array'             => 'Kentän :attribute arvo ei sisälly kentän :other arvoon.',
    'integer'              => 'Kentän :attribute arvon tulee olla numero.',
    'ip'                   => 'Kentän :attribute arvon tulee olla validi IP-osoite.',
    'ipv4'                 => 'The :attribute must be a valid IPv4 address.',
    'ipv6'                 => 'The :attribute must be a valid IPv6 address.',
    'json'                 => 'Kentän :attribute arvon tulee olla validia JSON:ia.',
    'max'                  => [
        'numeric' => 'Kentän arvon :attribute tulee olla enintään :max.',
        'file'    => 'Tiedoston :attribute tulee olla enintään :max kilobittiä.',
        'string'  => 'Kentän :attribute arvon tulee olla enintään :max merkkiä pitkä.',
        'array'   => 'Kentän :attribute ei tule sisältää enempää kuin :max arvoa.',
    ],
    'mimes'                => 'Kentän :attribute arvon tulee olla tiedostotyyppiä: :values.',
    'mimetypes'            => 'Kentän :attribute arvon tulee olla tiedostotyyppiä: :values.',
    'min'                  => [
        'numeric' => 'Kentän :attribute arvon tulee olla vähintään :min.',
        'file'    => 'Tiedoston :attribute tulee olla vähintään :min kilobittiä.',
        'string'  => 'Kentän :attribute arvon tulee olla vähintään :min merkkiä.',
        'array'   => 'Kentän :attribute tulee sisältää vähintään :min arvoa.',
    ],
    'not_in'               => 'Kentän :attribute arvo on virheellinen.',
    'numeric'              => 'Kentän :attribute arvon tulee olla numero.',
    'present'              => 'Kenttä :attribute vaaditaan.',
    'regex'                => 'Kentän :attribute arvo on väärää muotoa.',
    'required'             => 'Kenttä :attribute vaaditaan.',
    'required_if'          => 'Kenttä :attribute vaaditaan kun :other on :value.',
    'required_unless'      => 'Kenttä :attribute vaaditaan jos :other ei sisälly arvoihin :values.',
    'required_with'        => 'Kenttä :attribute vaaditaan kun arvo :values on annettu.',
    'required_with_all'    => 'Kenttä :attribute vaaditaan kun arvo :values on annettu.',
    'required_without'     => 'Kenttä :attribute vaaditaan kun arvoa :values ei ole annettu.',
    'required_without_all' => 'Kenttä :attribute vaaditaan kun mitään arvoista :values ei ole annettu.',
    'same'                 => 'Kenttien :attribute ja :other on oltava samanarvoiset.',
    'size'                 => [
        'numeric' => 'Kentän :attribute arvon tulee olla kokoa :size.',
        'file'    => 'Tiedoston :attribute tulee olla kokoa :size kilobittiä.',
        'string'  => 'Kentän :attribute arvon tulee olla kokoa :size merkkiä.',
        'array'   => 'Kentän :attribute tulee sisältää :size arvoa.',
    ],
    'string'               => 'Kentän :attribute arvon tulee olla tekstiä.',
    'timezone'             => 'Kentän :attribute arvon tulee olla validi aikavyöhyketunniste.',
    'unique'               => 'Kentän :attribute arvo ei ole uniikki.',
    'uploaded'             => 'Tiedoston :attribute lataus epäonnistui.',
    'url'                  => 'Kentän :attribute arvon tulee olla validi URL-osoite.',

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
