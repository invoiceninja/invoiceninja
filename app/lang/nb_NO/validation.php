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

	"accepted"         => ":attribute må være akseptert.",
	"active_url"       => ":attribute er ikke en gyldig URL.",
	"after"            => ":attribute må være en dato etter :date.",
	"alpha"            => ":attribute kan kun inneholde bokstaver.",
	"alpha_dash"       => ":attribute kan kun inneholde bokstaver, sifre, og bindestreker.",
	"alpha_num"        => ":attribute kan kun inneholde bokstaver og sifre.",
	"array"            => ":attribute må være en matrise.",
	"before"           => ":attribute må være en dato før :date.",
	"between"          => array(
		"numeric" => ":attribute må være mellom :min - :max.",
		"file"    => ":attribute må være mellom :min - :max kilobytes.",
		"string"  => ":attribute må være mellom :min - :max tegn.",
		"array"   => ":attribute må ha mellom :min - :max elementer.",
	),
	"confirmed"        => ":attribute bekreftelsen stemmer ikke",
	"date"             => ":attribute er ikke en gyldig dato.",
	"date_format"      => ":attribute samsvarer ikke med formatet :format.",
	"different"        => ":attribute og :other må være forskjellig.",
	"digits"           => ":attribute må være :digits sifre.",
	"digits_between"   => ":attribute må være mellom :min og :max sifre.",
	"email"            => ":attribute formatet er ugyldig.",
	"exists"           => "Valgt :attribute er ugyldig.",
	"image"            => ":attribute må være et bilde.",
	"in"               => "Valgt :attribute er ugyldig.",
	"integer"          => ":attribute må være heltall.",
	"ip"               => ":attribute må være en gyldig IP-adresse.",
	"max"              => array(
		"numeric" => "The :attribute may not be greater than :max.",
		"file"    => "The :attribute may not be greater than :max kilobytes.",
		"string"  => "The :attribute may not be greater than :max characters.",
		"array"   => "The :attribute may not have more than :max items.",
	),
	"mimes"            => "The :attribute must be a file of type: :values.",
	"min"              => array(
		"numeric" => "The :attribute must be at least :min.",
		"file"    => "The :attribute must be at least :min kilobytes.",
		"string"  => "The :attribute must be at least :min characters.",
		"array"   => "The :attribute must have at least :min items.",
	),
	"not_in"           => "The selected :attribute is invalid.",
	"numeric"          => "The :attribute must be a number.",
	"regex"            => "The :attribute format is invalid.",
	"required"         => "The :attribute field is required.",
	"required_if"      => "The :attribute field is required when :other is :value.",
	"required_with"    => "The :attribute field is required when :values is present.",
	"required_without" => "The :attribute field is required when :values is not present.",
	"same"             => "The :attribute and :other must match.",
	"size"             => array(
		"numeric" => "The :attribute must be :size.",
		"file"    => "The :attribute must be :size kilobytes.",
		"string"  => "The :attribute must be :size characters.",
		"array"   => "The :attribute must contain :size items.",
	),
	"unique"           => "The :attribute has already been taken.",
	"url"              => "The :attribute format is invalid.",
	
	"positive" => "The :attribute must be greater than zero.",
	"has_credit" => "The client does not have enough credit.",
	"notmasked" => "The values are masked",

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
