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

namespace App\DataMapper;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use App\DataMapper\EDoc\FatturaPA;

class EDocSettings extends Data
{
    public FatturaPA|Optional $FatturaPA;

    public function __construct() {}

    public function createFatturaPA(): FatturaPA
    {
        return $this->FatturaPA ??= new FatturaPA();
    }

}



class DatiAnagraficiVettore extends Data{

    public string $IdFiscaleIVA = '';
    public string $CodiceFiscale = '';
    public string $Anagrafica = '';
}

class DatiTrasporto extends Data{
    public string $DataOraConsegna = ''; //datetime in this format 2017-01-10T16:46:12.000+02:00
    //public DatiAnagraficiVettore
}

class DatiOrdineAcquisto extends Data{
    public string $RiferimentoNumeroLinea = '';
    public string $IdDocumento = '';
    public string $Data = '';
    public string $NumItem = '';
    public string $CodiceCommessaConvenzione = '';
    public string $CodiceCUP = '';
    public string $CodiceCIG = '';
}

class DatiContratto extends Data{
    public string $RiferimentoNumeroLinea = '';
    public string $IdDocumento = '';
    public string $Data = '';
    public string $NumItem = '';
    public string $CodiceCommessaConvenzione = '';
    public string $CodiceCUP = '';
    public string $CodiceCIG = '';
}

class DatiRicezione extends Data{
    public string $RiferimentoNumeroLinea = '';
    public string $IdDocumento = '';
    public string $Data = '';
    public string $NumItem = '';
    public string $CodiceCommessaConvenzione = '';
    public string $CodiceCUP = '';
    public string $CodiceCIG = '';
}