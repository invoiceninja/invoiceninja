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

"accepted" => ":attribute deve ser aceito.",
"active_url" => ":attribute não é uma URL válida.",
"after" => ":attribute deve ser uma data maior que :date.",
"alpha" => ":attribute deve conter apenas letras.",
"alpha_dash" => ":attribute pode conter apenas letras, número e traços",
"alpha_num" => ":attribute pode conter apenas letras e números.",
"array" => ":attribute deve ser um array.",
"before" => ":attribute deve ser uma data anterior a :date.",
"between" => array(
"numeric" => ":attribute deve ser entre :min - :max.",
"file" => ":attribute deve ser entre :min - :max kilobytes.",
"string" => ":attribute deve ser entre :min - :max caracteres.",
"array" => ":attribute deve ser entre :min - :max itens.",
),
"confirmed" => ":attribute confirmação não correponde.",
"date" => ":attribute não é uma data válida.",
"date_format" => ":attribute não satisfaz o formato :format.",
"different" => ":attribute e :other devem ser diferentes.",
"digits" => ":attribute deve conter :digits dígitos.",
"digits_between" => "The :attribute must be between :min and :max digits.",
"email" => "The :attribute format is invalid.",
"exists" => "The selected :attribute is invalid.",
"image" => "The :attribute must be an image.",
"in" => "The selected :attribute is invalid.",
"integer" => "The :attribute must be an integer.",
"ip" => "The :attribute must be a valid IP address.",
"max" => array(
"numeric" => "The :attribute may not be greater than :max.",
"file" => "The :attribute may not be greater than :max kilobytes.",
"string" => "The :attribute may not be greater than :max characters.",
"array" => "The :attribute may not have more than :max items.",
),
"mimes" => "The :attribute must be a file of type: :values.",
"min" => array(
"numeric" => "The :attribute must be at least :min.",
"file" => "The :attribute must be at least :min kilobytes.",
"string" => "The :attribute must be at least :min characters.",
"array" => "The :attribute must have at least :min items.",
),
"not_in" => "The selected :attribute is invalid.",
"numeric" => "The :attribute must be a number.",
"regex" => "The :attribute format is invalid.",
"required" => "The :attribute field is required.",
"required_if" => "The :attribute field is required when :other is :value.",
"required_with" => "The :attribute field is required when :values is present.",
"required_without" => "The :attribute field is required when :values is not present.",
"same" => "The :attribute and :other must match.",
"size" => array(
"numeric" => "The :attribute must be :size.",
"file" => "The :attribute must be :size kilobytes.",
"string" => "The :attribute must be :size characters.",
"array" => "The :attribute must contain :size items.",
),
"unique" => "The :attribute has already been taken.",
"url" => "The :attribute format is invalid.",

"positive" => "The :attribute must be greater than zero.",
"has_credit" => "The client does not have enough credit.",


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
