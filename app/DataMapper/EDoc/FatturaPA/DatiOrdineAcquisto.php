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

class DatiOrdineAcquisto extends Data
{
    public function __construct(
        public string $RiferimentoNumeroLinea = '',
        public string $IdDocumento = '',
        public string $Data = '',
        public string $NumItem = '',
        public string $CodiceCommessaConvenzione = '',
        public string $CodiceCUP = '',
        public string $CodiceCIG = '',
    ) {
    }
}
