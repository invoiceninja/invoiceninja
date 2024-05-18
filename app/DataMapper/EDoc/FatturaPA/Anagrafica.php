<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\EDoc\FatturaPA;

use Spatie\LaravelData\Data;
use Illuminate\Support\Optional;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\RequiredWith;
use Spatie\LaravelData\Attributes\Validation\RequiredWithoutAll;

class Anagrafica extends Data
{
     //choice

    //either Deminominazione OR
    //string length 80
    #[Max(80)]
    #[RequiredWithoutAll(['Nome','Cognome'])]
    public string|Optional $Denominazione = '';

    ////////////////////////////////////////////
    //2. Nome AND CogNome
    //string length 60
    #[Max(60)]
    #[RequiredWith('Cognome')]
    public string|Optional $Nome = '';

    //string length 60
    #[Max(60)]
    #[RequiredWith('Nome')]
    public string|Optional $CogNome = '';
}
