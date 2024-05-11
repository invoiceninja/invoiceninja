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
use App\DataMapper\EDoc\FatturaPA\Contatti;
use App\DataMapper\EDoc\FatturaPA\IscrizioneREA;

class CedentePrestatore extends Data
{
    public DatiAnagrafici $DatiAnagrafici;
    public Sede $sede;
    public Sede|Optional $StabileOrganizzazione;
    public IscrizioneREA|Optional $IscrizioneREA;
    public Contatti|Optional $Contatti;
    public string|Optional $RiferimentoAmministrazione;

}
