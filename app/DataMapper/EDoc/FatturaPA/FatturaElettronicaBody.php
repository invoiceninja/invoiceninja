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
use App\DataMapper\EDoc\FatturaPA\DatiVeicoli;
use App\DataMapper\EDoc\FatturaPA\DatiBeniServizi;
use App\DataMapper\EDoc\FatturaPA\Body\DatiGenerali;

class FatturaElettronicaBody extends Data
{

    public DatiGenerali $DatiGenerali;                                        
    public DatiBeniServizi $DatiBeniServizi;                                   
    public DatiVeicoli|Optional $DatiVeicoli;                       
    public DatiPagamento|Optional $DatiPagamento;
    public Allegati|Optional $Allegati;
    
}
