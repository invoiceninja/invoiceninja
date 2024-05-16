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

class IscrizioneREA extends Data
{
    //[A-Z]{2}
    public string $Ufficio;                      
    
    //string length 20
    public string|Optional $NumeroREA;                       
    
    //precision 2
    public float|Optional $CapitaleSociale;
    
    // options 
    // SU - socio unico (sole trader)
    // SN - piu soci (multiple shareholders)
    public string|Optional $SocioUnico; 

    // options
    // LS - In Liquidation
    // LN - Not in liquidation
    public string $StatoLiquidazione = 'LN';

}
