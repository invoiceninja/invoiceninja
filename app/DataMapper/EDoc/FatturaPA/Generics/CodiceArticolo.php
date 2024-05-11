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

namespace App\DataMapper\EDoc\FatturaPA\Generics;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Max;

class CodiceArticolo extends Data
{
    //string 35 char
    #[Max(35)]
    public string $CodiceTipo;
    
    //string 35 char
    #[Max(35)]
    public string $CodiceValore;
}

