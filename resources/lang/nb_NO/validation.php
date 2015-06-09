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

	"accepted"         => ":attribute m&#229; v&#230;re akseptert.",
	"active_url"       => ":attribute er ikke en gyldig URL.",
	"after"            => ":attribute m&#229; v&#230;re en dato etter :date.",
	"alpha"            => ":attribute kan kun inneholde bokstaver.",
	"alpha_dash"       => ":attribute kan kun inneholde bokstaver, sifre, og bindestreker.",
	"alpha_num"        => ":attribute kan kun inneholde bokstaver og sifre.",
	"array"            => ":attribute m&#229; v&#230;re en matrise.",
	"before"           => ":attribute m&#229; v&#230;re en dato f&#248;r :date.",
	"between"          => array(
		"numeric" => ":attribute m&#229; v&#230;re mellom :min - :max.",
		"file"    => ":attribute m&#229; v&#230;re mellom :min - :max kilobytes.",
		"string"  => ":attribute m&#229; v&#230;re mellom :min - :max tegn.",
		"array"   => ":attribute m&#229; ha mellom :min - :max elementer.",
	),
	"confirmed"        => ":attribute bekreftelsen stemmer ikke",
	"date"             => ":attribute er ikke en gyldig dato.",
	"date_format"      => ":attribute samsvarer ikke med formatet :format.",
	"different"        => ":attribute og :other m&#229; v&#230;re forskjellig.",
	"digits"           => ":attribute m&#229; v&#230;re :digits sifre.",
	"digits_between"   => ":attribute m&#229; v&#230;re mellom :min og :max sifre.",
	"email"            => ":attribute formatet er ugyldig.",
	"exists"           => "Valgt :attribute er ugyldig.",
	"image"            => ":attribute m&#229; v&#230;re et bilde.",
	"in"               => "Valgt :attribute er ugyldig.",
	"integer"          => ":attribute m&#229; v&#230;re heltall.",
	"ip"               => ":attribute m&#229; v&#230;re en gyldig IP-adresse.",
	"max"              => array(
		"numeric" => ":attribute kan ikke v&#230;re h&#248;yere enn :max.",
		"file"    => ":attribute kan ikke v&#230;re st&#248;rre enn :max kilobytes.",
		"string"  => ":attribute kan ikke v&#230;re mer enn :max tegn.",
		"array"   => ":attribute kan ikke inneholde mer enn :max elementer.",
	),
	"mimes"            => ":attribute m&#229; v&#230;re av filtypen: :values.",
	"min"              => array(
		"numeric" => ":attribute m&#229; minimum v&#230;re :min.",
		"file"    => ":attribute m&#229; minimum v&#230;re :min kilobytes.",
		"string"  => ":attribute m&#229; minimum v&#230;re :min tegn.",
		"array"   => ":attribute m&#229; inneholde minimum :min elementer.",
	),
	"not_in"           => "Valgt :attribute er ugyldig.",
	"numeric"          => ":attribute m&#229; v&#230;re et siffer.",
	"regex"            => ":attribute formatet er ugyldig.",
	"required"         => ":attribute er p&#229;krevd.",
	"required_if"      => ":attribute er p&#229;krevd n&#229;r :other er :value.",
	"required_with"    => ":attribute er p&#229;krevd n&#229;r :values er valgt.",
	"required_without" => ":attribute er p&#229;krevd n&#229;r :values ikke er valgt.",
	"same"             => ":attribute og :other m&#229; sammsvare.",
	"size"             => array(
		"numeric" => ":attribute m&#229; v&#230;re :size.",
		"file"    => ":attribute m&#229; v&#230;re :size kilobytes.",
		"string"  => ":attribute m&#229; v&#230;re :size tegn.",
		"array"   => ":attribute m&#229; inneholde :size elementer.",
	),
	"unique"           => ":attribute er allerede blitt tatt.",
	"url"              => ":attribute formatet er ugyldig.",
	
	"positive" => ":attribute m&#229; v&#230;re mer enn null.",
	"has_credit" => "Klienten har ikke h&#248;y nok kreditt.",
	"notmasked" => "Verdiene er skjult",
    "less_than" => 'The :attribute must be less than :value',
    
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
