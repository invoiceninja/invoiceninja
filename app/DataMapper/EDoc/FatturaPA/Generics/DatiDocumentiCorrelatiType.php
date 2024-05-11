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

namespace App\DataMapper\EDoc\FatturaPA\Generics;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiDocumentiCorrelatiType extends Data
{
        //String20Type
        public string $IdDocumento;
        //int 1-9999
        public int|Optional $RiferimentoNumeroLinea;
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public \DateTime|Optional $Data;
        //String20Type
        public string|Optional $NumItem;
        //String100LatinType
        public string|Optional $CodiceCommessaConvenzione;
        //String15Type
        public string|Optional $CodiceCUP;
        //String15Type
        public string|Optional $CodiceCIG;
}