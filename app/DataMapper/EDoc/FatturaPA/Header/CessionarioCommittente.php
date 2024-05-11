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
use CleverIt\UBL\Invoice\FatturaPA\common\Sede;
use App\DataMapper\EDoc\FatturaPA\RappresentanteFiscale;

class CessionarioCommittente extends Data
{
    public DatiAnagrafici $DatiAnagrafici;

    public Sede $Sede;

    public Sede|Optional $StabileOrganizzazione;

    public RappresentanteFiscale|Optional $RappresentanteFiscale;
}
