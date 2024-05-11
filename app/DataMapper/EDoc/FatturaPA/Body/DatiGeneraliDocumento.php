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

namespace App\DataMapper\EDoc\FatturaPA\Body;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use App\DataMapper\EDoc\FatturaPA\Body\DatiBollo;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Attributes\Validation\Regex;
use App\DataMapper\EDoc\FatturaPA\Body\DatiRitenuta;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiGeneraliDocumento extends Data
{

    //string regex [A-Z]{3}
    public string $Divisa;          

    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d\TH:i:s.uP')]
    public \Illuminate\Support\Carbon $Data;

    //string max 20 char
    #[Max(20)]
    public string $Numero;
    
    public DatiRitenuta|Optional $DatiRitenuta;
    public DatiBollo|Optional $DatiBollo;
    public DatiCassaPrevidenziale|Optional $DatiCassaPrevidenziale;
    public ScontoMaggiorazione|Optional $ScontoMaggiorazione;

    //float 2 decimal type
    #[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2}$/')]
    public float|Optional $ImportoTotaleDocumento;       

    //float 2 decimal type

    #[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2}$/')]
    public float|Optional $Arrotondamento;

    //string 200char
    /** @var string[] */
    public array|Optional $Causale;

    //SI = Documento emesso secondo modalità e termini stabiliti con DM ai sensi dell'art. 73 DPR 633/72
    //optional 2 char - only value possible = SI
    #[Size(2)]
    public string|Optional $Art73;  
        
    //length 4 - optional
    #[Size(4)]
    public string $TipoDocumento = 'TD01';         

}
