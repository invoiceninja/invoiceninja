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

namespace App\DataMapper\EDoc;

use Spatie\LaravelData\Data;

use Spatie\LaravelData\Optional;
use App\DataMapper\EDoc\FatturaPA\DatiContratto;
use App\DataMapper\EDoc\FatturaPA\DatiRicezione;
use App\DataMapper\EDoc\FatturaPA\DatiOrdineAcquisto;
use App\DataMapper\EDoc\FatturaPA\DatiAnagraficiVettore;

class FatturaPA extends Data
{
    public DatiRicezione|Optional $DatiRicezione;
    public DatiContratto|Optional $DatiContratto;
    public DatiOrdineAcquisto|Optional $DatiOrdineAcquisto;
    public DatiAnagraficiVettore|Optional $DatiAnagraficiVettore;

    public function __construct(
        public string $RegimeFiscale = 'RF01',
        public string $TipoDocumento = 'TD01',
        public string $ModalitaPagamento = 'MP01',
        public string $CondizioniPagamento = 'TP02',
    ) {
    }

}
