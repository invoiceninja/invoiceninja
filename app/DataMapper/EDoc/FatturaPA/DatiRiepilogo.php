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
use Spatie\LaravelData\Optional;

class DatiRiepilogo extends Data
{

    //float 0-100.0
    public float $AliquotaIVA; 
    
    //Amount2DecimalType
    public float $ImponibileImporto;

    //Amount2DecimalType
    public float $Imposta;   

    //string - options
    public string|Optional $Natura;        

    //Amount2DecimalType
    public float|Optional $SpeseAccessorie;    

    //Amount8DecimalType
    public float|Optional $Arrotondamento;    

    //string options D/I/S
    public float|Optional $EsigibilitaIVA;      
    
    //String100LatinType
    public string|Optional $RiferimentoNormativo;

}


