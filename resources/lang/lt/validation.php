<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Patvirtinimo kalbos eilutės
    |--------------------------------------------------------------------------
    |
    | Sekančios kalbos eilutėse yra numatyti klaidos pranešimai naudojami
    | patvirtinimo klasėje. Kai kurios iš šių eilučių turi keletą versijų
    | tokių kaip dydžio taisyklės. Galite laisvai pataisyti bet kuriuos pranešimus.
    |
    */

    "accepted"             => "Laukas :attribute turi būti priimtas.",
    "active_url"           => "Laukas :attribute nėra galiojantis internetinis adresas.",
    "after"                => "Laukelyje :attribute turi būti data po :date.",
    "alpha"                => "Laukas :attribute gali turėti tik raides.",
    "alpha_dash"           => "Laukas :attribute gali turėti tik raides, skaičius ir brūkšnelius.",
    "alpha_num"            => "Laukas :attribute gali turėti tik raides ir skaičius.",
    "array"                => "Laukas :attribute turi būti masyvas.",
    "before"               => "Laukas :attribute turi būti data prieš :date.",
    "between"              => [
        "numeric" => "Lauko :attribute reikšmė turi būti tarp :min ir :max.",
        "file"    => "Failo dydis lauke :attribute turi būti tarp :min ir :max kilobaitų.",
        "string"  => "Simbolių skaičius lauke :attribute turi būti tarp :min ir :max.",
        "array"   => "Elementų skaičius lauke :attribute turi turėti nuo :min iki :max.",
    ],
    "boolean"              => "Lauko reikšmė :attribute turi būti 'taip' arba 'ne'.",
    "confirmed"            => "Lauko :attribute patvirtinimas nesutampa.",
    "date"                 => "Lauko :attribute reikšmė nėra galiojanti data.",
    "date_format"          => "Lauko :attribute reikšmė neatitinka formato :format.",
    "different"            => "Laukų :attribute ir :other reikšmės turi skirtis.",
    "digits"               => "Laukas :attribute turi būti sudarytas iš :digits skaitmenų.",
    "digits_between"       => "Laukas :attribute tuti turėti nuo :min iki :max skaitmenų.",
    "email"                => "Lauko :attribute reikšmė turi būti galiojantis el. pašto adresas.",
    "filled"               => "Laukas :attribute turi būti užpildytas.",
    "exists"               => "Pasirinkta negaliojanti :attribute reikšmė.",
    "image"                => "Lauko :attribute reikšmė turi būti paveikslėlis.",
    "in"                   => "Pasirinkta negaliojanti :attribute reikšmė.",
    "integer"              => "Lauko :attribute reikšmė turi būti veikasis skaičius.",
    "ip"                   => "Lauko :attribute reikšmė turi būti galiojantis IP adresas.",
    "max"                  => [
        "numeric" => "Lauko :attribute reikšmė negali būti didesnė nei :max.",
        "file"    => "Failo dydis lauke :attribute reikšmė negali būti didesnė nei :max kilobaitų.",
        "string"  => "Simbolių kiekis lauke :attribute reikšmė negali būti didesnė nei :max simbolių.",
        "array"   => "Elementų kiekis lauke :attribute negali turėti daugiau nei :max elementų.",
    ],
    "mimes"                => "Lauko reikšmė :attribute turi būti failas vieno iš sekančių tipų: :values.",
    "min"                  => [
        "numeric" => "Lauko :attribute reikšmė turi būti ne mažesnė nei :min.",
        "file"    => "Failo dydis lauke :attribute turi būti ne mažesnis nei :min kilobaitų.",
        "string"  => "Simbolių kiekis lauke :attribute turi būti ne mažiau nei :min.",
        "array"   => "Elementų kiekis lauke :attribute turi būti ne mažiau nei :min.",
    ],
    "not_in"               => "Pasirinkta negaliojanti reikšmė :attribute.",
    "numeric"              => "Lauko :attribute reikšmė turi būti skaičius.",
    "regex"                => "Negaliojantis lauko :attribute formatas.",
    "required"             => "Privaloma užpildyti lauką :attribute.",
    "required_if"          => "Privaloma užpildyti lauką :attribute kai :other yra :value.",
    "required_with"        => "Privaloma užpildyti lauką :attribute kai pateikta :values.",
    "required_with_all"    => "Privaloma užpildyti lauką :attribute kai pateikta :values.",
    "required_without"     => "Privaloma užpildyti lauką :attribute kai nepateikta :values.",
    "required_without_all" => "Privaloma užpildyti lauką :attribute kai nepateikta nei viena iš reikšmių :values.",
    "same"                 => "Laukai :attribute ir :other turi sutapti.",
    "size"                 => [
        "numeric" => "Lauko :attribute reikšmė turi būti :size.",
        "file"    => "Failo dydis lauke :attribute turi būti :size kilobaitai.",
        "string"  => "Simbolių skaičius lauke :attribute turi būti :size.",
        "array"   => "Elementų kiekis lauke :attribute turi būti :size.",
    ],
    "string"               => "The :attribute must be a string.",
    "timezone"             => "Lauko :attribute reikšmė turi būti galiojanti laiko zona.",
    "unique"               => "Tokia :attribute reikšmė jau pasirinkta.",
    "url"                  => "Negaliojantis lauko :attribute formatas.",

    /*
    |--------------------------------------------------------------------------
    | Pasirinktiniai patvirtinimo kalbos eilutės
    |--------------------------------------------------------------------------
    |
    | Čia galite nurodyti pasirinktinius patvirtinimo pranešimus, naudodami
    | konvenciją "attribute.rule" eilučių pavadinimams. Tai leidžia greitai
    | nurodyti konkrečią pasirinktinę kalbos eilutę tam tikrai atributo taisyklei.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pasirinktiniai patvirtinimo atributai
    |--------------------------------------------------------------------------
    |
    | Sekančios kalbos eilutės naudojamos pakeisti vietos žymes
    | kuo nors labiau priimtinu skaitytojui (pvz. "El.Pašto Adresas" vietoj
    | "email". TTai tiesiog padeda mums padaryti žinutes truputi aiškesnėmis.
    |
    */

    'attributes' => [],

];
