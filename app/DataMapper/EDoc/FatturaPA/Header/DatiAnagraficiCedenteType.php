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
use App\DataMapper\EDoc\FatturaPA\Header\IdFiscaleIVA;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiAnagraficiCedenteType extends Data
{
    public function __construct(

        public Anagrafica $Anagrafica,                  
        
        public IdFiscaleIVA|Optional $IdFiscaleIVA,
        
        // string length 4 -  options  = 'RF01'
        #[Size(4)]
        public string|Optional $RegimeFiscale,
        
        //[A-Z0-9]{11,16}
        #[Regex('/^[A-Z0-9]{11,16}$/')]
        public string|Optional $CodiceFiscale,

        //string 60 char
        #[Max(20)]
        public string|Optional $AlboProfessionale,

        //string 2 chat [A-Z]{2}
        #[Size(2)]
        #[Regex('/^[A-Z]{2}$/')]
        public string|Optional $ProvinciaAlbo,
        
        //string 60 char
        #[Max(60)]
        public string|Optional $NumeroIscrizioneAlbo,
        
        //Date
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public \Datetime|Optional $DataIscrizioneAlbo,
    ){}
}