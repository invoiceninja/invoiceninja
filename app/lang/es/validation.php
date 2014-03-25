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

	"accepted"         => ":attribute debe ser aceptado.",
	"active_url"       => ":attribute no es una URL válida.",
	"after"            => ":attribute debe ser una fecha posterior a :date.",
	"alpha"            => ":attribute sólo debe contener letras.",
	"alpha_dash"       => ":attribute sólo debe contener letras, números y guiones.",
	"alpha_num"        => ":attribute sólo debe contener letras y números.",
	"array"            => ":attribute debe ser un array.",
	"before"           => ":attribute debe ser una fecha anterior a :date.",
	"between"          => array(
		"numeric" => ":attribute debe estar entre :min y :max.",
		"file"    => ":attribute debe estar entre :min y :max kilobytes.",
		"string"  => ":attribute debe estar entre :min y :max caracteres.",
		"array"   => ":attribute debe estar entre :min y :max items.",
	),
	"confirmed"        => ":attribute no coincide con la confirmación.",
	"date"             => ":attribute no es una fecha válida.",
	"date_format"      => ":attribute no coincide con el formato :format.",
	"different"        => ":attribute y :other deben ser diferentes.",
	"digits"           => ":attribute debe contener :digits dígitos.",
	"digits_between"   => ":attribute debe contener entre :min y :max dígitos.",
	"email"            => ":attribute tiene un formato no válido.",
	"exists"           => "no se ha selecionado un valor de :attribute válido.",
	"image"            => ":attribute debe ser una imágen.",
	"in"               => "el valor de :attribute no es válido.",
	"integer"          => ":attribute debe ser un número entero.",
	"ip"               => ":attribute debe ser una dirección IP válida.",
	"max"              => array(
		"numeric" => ":attribute no debe ser mayor que :max.",
		"file"    => ":attribute no debe ser mayor de :max kilobytes.",
		"string"  => ":attribute no debe ser mayor de :max characters.",
		"array"   => ":attribute no debe tener más de :max elementos.",
	),
	"mimes"            => ":attribute debe ser un archivo de tipo: :values.",
	"min"              => array(
		"numeric" => "The :attribute debe ser al menos :min.",
		"file"    => "The :attribute debe ser al menos :min kilobytes.",
		"string"  => "The :attribute debe ser al menos :min characters.",
		"array"   => "The :attribute debe contener al menos :min elementos.",
	),
	"not_in"           => "el valor de :attribute no es válido.",
	"numeric"          => ":attribute debe ser un número.",
	"regex"            => "el formato de :attribute no es válido.",
	"required"         => "el campo :attribute es necesario.",
	"required_if"      => "el campo :attribute es necesario cuando :other es :value.",
	"required_with"    => "el campo :attribute es necesario cuando :values está presente.",
	"required_without" => "el campo :attribute es necesario cuando :values no está presente.",
	"same"             => ":attribute y :other deben coincidir.",
	"size"             => array(
		"numeric" => ":attribute debe ser :size.",
		"file"    => ":attribute debe ser :size kilobytes.",
		"string"  => ":attribute debe ser :size caracteres.",
		"array"   => ":attribute debe contener :size elementos.",
	),
	"unique"     => ":attribute ya está en uso.",
	"url"        => ":attribute tiene un formato no válido.",
	"positive"   => ":attribute debe ser mayor que cero.",
	"has_credit" => "el cliente no tiene crédito suficiente.",


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
