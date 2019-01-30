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
"alpha_dash" => ":attribute pode conter apenas letras, número e hífens",
"alpha_num" => ":attribute pode conter apenas letras e números.",
"array" => ":attribute deve ser uma lista.",
"before" => ":attribute deve ser uma data anterior a :date.",
"between" => array(
    "numeric" => ":attribute deve estar entre :min - :max.",
    "file" => ":attribute deve estar entre :min - :max kilobytes.",
    "string" => ":attribute deve estar entre :min - :max caracteres.",
    "array" => ":attribute deve conter entre :min - :max itens.",
    ),
"confirmed" => ":attribute confirmação não corresponde.",
"date" => ":attribute não é uma data válida.",
"date_format" => ":attribute não satisfaz o formato :format.",
"different" => ":attribute e :other devem ser diferentes.",
"digits" => ":attribute deve conter :digits dígitos.",
"digits_between" => ":attribute deve conter entre :min e :max dígitos.",
"email" => ":attribute está em um formato inválido.",
"exists" => "A opção selecionada :attribute é inválida.",
"image" => ":attribute deve ser uma imagem.",
"in" => "A opção selecionada :attribute é inválida.",
"integer" => ":attribute deve ser um número inteiro.",
"ip" => ":attribute deve ser um endereço IP válido.",
"max" => array(
    "numeric" => ":attribute não pode ser maior que :max.",
    "file" => ":attribute não pode ser maior que :max kilobytes.",
    "string" => ":attribute não pode ser maior que :max caracteres.",
    "array" => ":attribute não pode conter mais que :max itens.",
    ),
"mimes" => ":attribute deve ser um arquivo do tipo: :values.",
"min" => array(
    "numeric" => ":attribute não deve ser menor que :min.",
    "file" => ":attribute deve ter no mínimo :min kilobytes.",
    "string" => ":attribute deve conter no mínimo :min caracteres.",
    "array" => ":attribute deve conter ao menos :min itens.",
    ),
"not_in" => "A opção selecionada :attribute é inválida.",
"numeric" => ":attribute deve ser um número.",
"regex" => ":attribute está em um formato inválido.",
"required" => ":attribute é um campo obrigatório.",
"required_if" => ":attribute é necessário quando :other é :value.",
"required_with" => ":attribute é obrigatório quando :values está presente.",
"required_without" => ":attribute é obrigatório quando :values não está presente.",
"same" => ":attribute e :other devem corresponder.",
"size" => array(
    "numeric" => ":attribute deve ter :size.",
    "file" => ":attribute deve ter :size kilobytes.",
    "string" => ":attribute deve conter :size caracteres.",
    "array" => ":attribute deve conter :size itens.",
    ),
"unique" => ":attribute já está sendo utilizado.",
"url" => ":attribute está num formato inválido.",

"positive" => ":attribute deve ser maior que zero.",
"has_credit" => "O cliente não possui crédito suficiente.",
"notmasked" => "Os valores são mascarados",
"less_than" => ':attribute deve ser menor que :value',
"has_counter" => 'O valor deve conter {$counter}',
"valid_contacts" => "Todos os contatos devem conter um e-mail ou nome",
"valid_invoice_items" => "Esta fatura excedeu o número mximo de itens",

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
