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
use App\DataMapper\EDoc\FatturaPA\Header\DatiTrasmissione;
use App\DataMapper\EDoc\FatturaPA\Header\CedentePrestatore;
use App\DataMapper\EDoc\FatturaPA\Header\CessionarioCommittente;

class FatturaElettronicaHeader extends Data
{
    public DatiTrasmissione $DatiTrasmissione; //DatiTrasmissione
    public CedentePrestatore $CedentePrestatore; //CedentePrestatore
    public CessionarioCommittente $CessionarioCommittente; //CessionarioCommittente
}
