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

class DatiTrasmissione extends Data
{
    public IdTrasmittente $IdTrasmittente; //IdTrasmittente
    public string $ProgressivoInvio = ''; //String
    public string $FormatoTrasmissione = ''; //String
    public string $CodiceDestinatario = ''; //String
    
    
}
