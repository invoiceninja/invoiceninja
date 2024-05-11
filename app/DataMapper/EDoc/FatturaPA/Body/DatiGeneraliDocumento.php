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
use Spatie\LaravelData\Attributes\WithTransformer;
use App\DataMapper\EDoc\FatturaPA\Body\DatiRitenuta;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiGeneraliDocumento extends Data
{
    //length 4 - optional
    public string $TipoDocumento;         

    //string regex [A-Z]{3}
    public string $Divisa;          

    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public \DateTime $Data;

    //string max 20 char
    public string $Numero;
    
    public DatiRitenuta|Optional $DatiRitenuta;
    public DatiBollo|Optional $DatiBollo;
    public DatiCassaPrevidenziale|Optional $DatiCassaPrevidenziale;
    public ScontoMaggiorazione|Optional $ScontoMaggiorazione;

    //float 2 decimal type
    public float|Optional $ImportoTotaleDocumento;       

    //float 2 decimal type
    public float|Optional $Arrotondamento;   
    //string 200char
    public string|Optional $Causale;

    //SI = Documento emesso secondo modalità e termini stabiliti con DM ai sensi dell'art. 73 DPR 633/72
    //optional 2 char - only value possible = SI
    public string|Optional $Art73;      

}
