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

use App\DataMapper\EDoc\FatturaPA\Header\IdFiscaleIVA;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class RappresentanteFiscale extends Data
{
    public IdFiscaleIVA $IdFiscaleIVA;

    public Anagrafica $Anagrafica;

    //   <xs:pattern value="[A-Z0-9]{11,16}" />
    public string|Optional $CodiceFiscaleType;
}
