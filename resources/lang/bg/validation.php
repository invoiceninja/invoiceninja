<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages.
    |
    */

    'accepted'             => 'Трябва да приемете :attribute.',
    'active_url'           => 'Полето :attribute не е валиден URL адрес.',
    'after'                => 'Полето :attribute трябва да бъде дата след :date.',
    'after_or_equal'       => 'Полето :attribute трябва да бъде дата след или равна на :date.',
    'alpha'                => 'Полето :attribute трябва да съдържа само букви.',
    'alpha_dash'           => 'Полето :attribute трябва да съдържа само букви, цифри, долна черта и тире.',
    'alpha_num'            => 'Полето :attribute трябва да съдържа само букви и цифри.',
    'array'                => 'Полето :attribute трябва да бъде масив.',
    'before'               => 'Полето :attribute трябва да бъде дата преди :date.',
    'before_or_equal'      => 'Полето :attribute трябва да бъде дата преди или равна на :date.',
    'between'              => [
        'numeric' => 'Полето :attribute трябва да бъде между :min и :max.',
        'file'    => 'Полето :attribute трябва да бъде между :min и :max килобайта.',
        'string'  => 'Полето :attribute трябва да бъде между :min и :max знака.',
        'array'   => 'Полето :attribute трябва да има между :min - :max елемента.',
    ],
    'boolean'              => 'Полето :attribute трябва да съдържа Да или Не',
    'confirmed'            => 'Полето :attribute не е потвърдено.',
    'date'                 => 'Полето :attribute не е валидна дата.',
    'date_equals'          => 'The :attribute must be a date equal to :date.',
    'date_format'          => 'Полето :attribute не е във формат :format.',
    'different'            => 'Полетата :attribute и :other трябва да са различни.',
    'digits'               => 'Полето :attribute трябва да има :digits цифри.',
    'digits_between'       => 'Полето :attribute трябва да има между :min и :max цифри.',
    'dimensions'           => 'Невалидни размери за снимка :attribute.',
    'distinct'             => 'Данните в полето :attribute се дублират.',
    'email'                => 'Полето :attribute е в невалиден формат.',
    'exists'               => 'Избранато поле :attribute вече съществува.',
    'file'                 => 'Полето :attribute трябва да бъде файл.',
    'filled'               => 'Полето :attribute е задължително.',
    'gt'                   => [
        'numeric' => 'The :attribute must be greater than :value.',
        'file'    => 'The :attribute must be greater than :value kilobytes.',
        'string'  => 'The :attribute must be greater than :value characters.',
        'array'   => 'The :attribute must have more than :value items.',
    ],
    'gte'                  => [
        'numeric' => 'The :attribute must be greater than or equal :value.',
        'file'    => 'The :attribute must be greater than or equal :value kilobytes.',
        'string'  => 'The :attribute must be greater than or equal :value characters.',
        'array'   => 'The :attribute must have :value items or more.',
    ],
    'image'                => 'Полето :attribute трябва да бъде изображение.',
    'in'                   => 'Избраното поле :attribute е невалидно.',
    'in_array'             => 'Полето :attribute не съществува в :other.',
    'integer'              => 'Полето :attribute трябва да бъде цяло число.',
    'ip'                   => 'Полето :attribute трябва да бъде IP адрес.',
    'ipv4'                 => 'Полето :attribute трябва да бъде IPv4 адрес.',
    'ipv6'                 => 'Полето :attribute трябва да бъде IPv6 адрес.',
    'json'                 => 'Полето :attribute трябва да бъде JSON низ.',
    'lt'                   => [
        'numeric' => 'The :attribute must be less than :value.',
        'file'    => 'The :attribute must be less than :value kilobytes.',
        'string'  => 'The :attribute must be less than :value characters.',
        'array'   => 'The :attribute must have less than :value items.',
    ],
    'lte'                  => [
        'numeric' => 'The :attribute must be less than or equal :value.',
        'file'    => 'The :attribute must be less than or equal :value kilobytes.',
        'string'  => 'The :attribute must be less than or equal :value characters.',
        'array'   => 'The :attribute must not have more than :value items.',
    ],
    'max'                  => [
        'numeric' => 'Полето :attribute трябва да бъде по-малко от :max.',
        'file'    => 'Полето :attribute трябва да бъде по-малко от :max килобайта.',
        'string'  => 'Полето :attribute трябва да бъде по-малко от :max знака.',
        'array'   => 'Полето :attribute трябва да има по-малко от :max елемента.',
    ],
    'mimes'                => 'Полето :attribute трябва да бъде файл от тип: :values.',
    'mimetypes'            => 'Полето :attribute трябва да бъде файл от тип: :values.',
    'min'                  => [
        'numeric' => 'Полето :attribute трябва да бъде минимум :min.',
        'file'    => 'Полето :attribute трябва да бъде минимум :min килобайта.',
        'string'  => 'Полето :attribute трябва да бъде минимум :min знака.',
        'array'   => 'Полето :attribute трябва има минимум :min елемента.',
    ],
    'not_in'               => 'Избраното поле :attribute е невалидно.',
    'not_regex'            => 'The :attribute format is invalid.',
    'numeric'              => 'Полето :attribute трябва да бъде число.',
    'present'              => 'Полето :attribute трябва да съествува.',
    'regex'                => 'Полето :attribute е в невалиден формат.',
    'required'             => 'Полето :attribute е задължително.',
    'required_if'          => 'Полето :attribute се изисква, когато :other е :value.',
    'required_unless'      => 'Полето :attribute се изисква, освен ако :other не е в :values.',
    'required_with'        => 'Полето :attribute се изисква, когато :values има стойност.',
    'required_with_all'    => 'Полето :attribute е задължително, когато :values имат стойност.',
    'required_without'     => 'Полето :attribute се изисква, когато :values няма стойност.',
    'required_without_all' => 'Полето :attribute се изисква, когато никое от полетата :values няма стойност.',
    'same'                 => 'Полетата :attribute и :other трябва да съвпадат.',
    'size'                 => [
        'numeric' => 'Полето :attribute трябва да бъде :size.',
        'file'    => 'Полето :attribute трябва да бъде :size килобайта.',
        'string'  => 'Полето :attribute трябва да бъде :size знака.',
        'array'   => 'Полето :attribute трябва да има :size елемента.',
    ],
    'starts_with'          => 'The :attribute must start with one of the following: :values',
    'string'               => 'Полето :attribute трябва да бъде знаков низ.',
    'timezone'             => 'Полето :attribute трябва да съдържа валидна часова зона.',
    'unique'               => 'Полето :attribute вече съществува.',
    'uploaded'             => 'Неуспешно качване на :attribute.',
    'url'                  => 'Полето :attribute е в невалиден формат.',
    'uuid'                 => 'The :attribute must be a valid UUID.',

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

    'attributes' => [
        'name'                     => 'Име',
        'username'                 => 'Потребител',
        'email'                    => 'E-mail',
        'first_name'               => 'Име',
        'last_name'                => 'Фамилия',
        'password'                 => 'Парола',
        'city'                     => 'Град',
        'country'                  => 'Държава',
        'address'                  => 'Адрес',
        'phone'                    => 'Телефон',
        'mobile'                   => 'GSM',
        'age'                      => 'Възраст',
        'sex'                      => 'Пол',
        'gender'                   => 'Пол',
        'day'                      => 'Ден',
        'month'                    => 'Месец',
        'year'                     => 'Година',
        'hour'                     => 'Час',
        'minute'                   => 'Минута',
        'second'                   => 'Секунда',
        'title'                    => 'Заглавие',
        'content'                  => 'Съдържание',
        'description'              => 'Описание',
        'excerpt'                  => 'Откъс',
        'date'                     => 'Дата',
        'time'                     => 'Време',
        'available'                => 'Достъпен',
        'size'                     => 'Размер',
        'recaptcha_response_field' => 'Рекапча',
        'subject'                  => 'Заглавие',
        'message'                  => 'Съобщение',
    ],
];
