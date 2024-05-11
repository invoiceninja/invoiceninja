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
use App\DataMapper\EDoc\FatturaPA\Sede;
use App\DataMapper\EDoc\FatturaPA\RappresentanteFiscale;
use App\DataMapper\EDoc\FatturaPA\Header\DatiAnagraficiCedenteType;

class CessionarioCommittente extends Data
{
    public DatiAnagraficiCedenteType $DatiAnagrafici;
    
    /** @var Sede[] */
    public array $Sede;

    /** @var Sede[] */
    public array|Optional $StabileOrganizzazione;

    public RappresentanteFiscale|Optional $RappresentanteFiscale;
}
