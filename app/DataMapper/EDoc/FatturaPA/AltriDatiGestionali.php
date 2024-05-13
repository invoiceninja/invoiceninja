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
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class AltriDatiGestionali extends Data
{
    //string 10
    #[Max(10)]
    public string $TipoDato;         

    //String60LatinType
    #[Max(60)]
    public string|Optional $RiferimentoTesto;

    //Amount8DecimalType
    #[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2,8}$/')]
    public float|Optional $RiferimentoNumero;

    //date
    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public Carbon|Optional $RiferimentoData;
}



