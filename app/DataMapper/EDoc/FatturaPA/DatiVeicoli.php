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
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiVeicoli extends Data
{
    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public Carbon|Optional $Data;

    //String15Type
    public string $TotalePercorso;

    public function __construct()
    {
        // $this->Data = new \DateTime();
    }
}