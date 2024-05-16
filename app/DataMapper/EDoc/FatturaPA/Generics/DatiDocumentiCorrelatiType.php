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
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiDocumentiCorrelatiType extends Data
{
        //String20Type
        #[Max(20)]
        public string $IdDocumento;
        //int 1-9999
        #[Between(1,9999)]
        public int|Optional $RiferimentoNumeroLinea;
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public \Illuminate\Support\Carbon|Optional $Data;
        //String20Type
        #[Max(20)]
        public string|Optional $NumItem;
        //String100LatinType
        #[Max(100)]
        public string|Optional $CodiceCommessaConvenzione;
        //String15Type
        #[Max(15)]
        public string|Optional $CodiceCUP;
        //String15Type
        #[Max(15)]
        public string|Optional $CodiceCIG;
}