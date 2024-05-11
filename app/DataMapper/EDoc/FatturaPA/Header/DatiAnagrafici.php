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
use Spatie\LaravelData\Optional;
use App\DataMapper\EDoc\FatturaPA\Anagrafica;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiAnagrafici extends Data
{
    public IdFiscaleIVA $IdFiscaleIVA;
    public Anagrafica $Anagrafica;                    

    // string length 4 -  options
    public string|Optional $RegimeFiscale;
    
    //[A-Z0-9]{11,16}
    public string|Optional $CodiceFiscale;

    //string 60 char
    public string|Optional $AlboProfessionale;

    //string 2 chat [A-Z]{2}
    public string|Optional $ProvinciaAlbo;
    
    //string 60 char
    public string|Optional $NumeroIscrizioneAlbo;
    
    //Date
    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public \DateTime|Optional $DataIscrizioneAlbo;

}
