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

class FatturaPA extends Data
{
    public string $RegimeFiscale = 'RF01';
    public string $TipoDocumento = 'TD01';
    public string $ModalitaPagamento = 'MP01';
    public string $CondizioniPagamento = 'TP02';

}
