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

namespace App\DataMapper\EDoc\FatturaPA\Header;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Size;

class DatiTrasmissione extends Data
{
    public IdTrasmittente $IdTrasmittente; //IdTrasmittente

    #[Max(10)]
    public string $ProgressivoInvio = ''; //String

    #[Size(5)]
    public string $FormatoTrasmissione = ''; //String
   
    #[Regex('/^[A-Z0-9]{6,7}$/')]
    public string $CodiceDestinatario = ''; //String
    
    
}
