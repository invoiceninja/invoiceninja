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

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class Anagrafica extends Data
{
     //choice

    //either Deminominazione OR
    //string length 80
    #[Max(80)]
    public string $Denominazione = '';

    ////////////////////////////////////////////
    //2. Nome AND CogNome
    //string length 60
    #[Max(60)]
    public string $Nome = '';

    //string length 60
    #[Max(60)]
    public string $CogNome = '';
}
