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

    "accepted"         => ":attribute はmust be accepted.",
    "active_url"       => ":attribute は正しいURLではありません。",
    "after"            => ":attribute は:date以降の日付である必要があります。",
    "alpha"            => ":attribute は半角英字のみ可能です。",
    "alpha_dash"       => ":attribute は半角英数字およびハイフンのみ可能です。",
    "alpha_num"        => ":attribute は半角英数字のみ可能です。",
    "array"            => "The :attribute must be an array.",
    "before"           => ":attribute は:date以前の日付である必要があります。",
    "between"          => array(
        "numeric" => ":attribute は :min - :max の範囲です。",
        "file"    => ":attribute は :min - :max KBの範囲です。",
        "string"  => ":attribute は :min - :max 文字の範囲です。",
        "array"   => ":attribute は :min - :max 個の範囲です。",
    ),
    "confirmed"        => "The :attribute confirmation does not match.",
    "date"             => "The :attribute is not a valid date.",
    "date_format"      => "The :attribute does not match the format :format.",
    "different"        => "The :attribute and :other must be different.",
    "digits"           => "The :attribute must be :digits digits.",
    "digits_between"   => "The :attribute must be between :min and :max digits.",
    "email"            => "The :attribute format is invalid.",
    "exists"           => "The selected :attribute is invalid.",
    "image"            => "The :attribute must be an image.",
    "in"               => "The selected :attribute is invalid.",
    "integer"          => "The :attribute must be an integer.",
    "ip"               => "The :attribute must be a valid IP address.",
    "max"              => array(
        "numeric" => ":attribute は:max 以下の必要があります。",
        "file"    => ":attribute は:max KB以下の必要があります。",
        "string"  => ":attribute は:max 文字以下の必要があります。",
        "array"   => ":attribute は:max 個以下の必要があります。",
    ),
    "mimes"            => ":attribute は以下のファイル・タイプの必要があります。 :values.",
    "min"              => array(
        "numeric" => ":attribute は:min 以上の必要があります。",
        "file"    => ":attribute は:min KB以上の必要があります。",
        "string"  => ":attribute は:min 文字以上の必要があります。",
        "array"   => ":attribute は:min 個以上の必要があります。",
    ),
    "not_in"           => "選択された :attribute は正しくありません。",
    "numeric"          => ":attribute は数値の必要があります。",
    "regex"            => ":attribute のフォーマットが正しくありません。",
    "required"         => ":attribute フィールドが必要です。",
    "required_if"      => ":other が :valueの場合、:attribute フィールドが必要です。",
    "required_with"    => "The :attribute field is required when :values is present.",
    "required_without" => "The :attribute field is required when :values is not present.",
    "same"             => ":attribute と :other が一致していません。",
    "size"             => array(
        "numeric" => "The :attribute must be :size.",
        "file"    => "The :attribute must be :size kilobytes.",
        "string"  => "The :attribute must be :size characters.",
        "array"   => "The :attribute must contain :size items.",
    ),
    "unique"           => ":attribute は既に使われています。",
    "url"              => ":attribute のフォーマットが正しくありません。",

    "positive" => "The :attribute must be greater than zero.",
    "has_credit" => "The client does not have enough credit.",
    "notmasked" => "The values are masked",
    "less_than" => "The :attribute must be less than :value",
    "has_counter" => "The value must contain {\$counter}",
    "valid_contacts" => "The contact must have either an email or name",
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
