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
use App\DataMapper\EDoc\FatturaPA\Header\IdFiscaleIVA;

class DatiAnagraficiVettore extends Data
{
        public IdFiscaleIVA $IdFiscaleIVA;
        
        public Anagrafica $Anagrafica;
        
        public string|Optional $CodiceFiscale;
        
        //String20Type
        public string|Optional $NumeroLicenzaGuida;
}
