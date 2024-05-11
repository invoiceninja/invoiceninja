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
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class AltriDatiGestionali extends Data
{
    //string 10
    public string $TipoDato;         

    //String60LatinType
    public string|Optional $RiferimentoTesto;

    //Amount8DecimalType
    public float|Optional $RiferimentoNumero;

    //date
    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public \DateTime|Optional $RiferimentoData;
}



