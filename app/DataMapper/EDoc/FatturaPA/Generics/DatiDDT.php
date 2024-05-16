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

use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiDDT extends Data
{
    //String20Type
    public string $NumeroDDT;

    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public \Illuminate\Support\Carbon $DataDDT;
    
    //int 1-9999
    #[Between(1,9999)]
    public int|Optional $RiferimentoNumeroLinea;

}
