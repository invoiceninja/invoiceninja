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
use App\DataMapper\EDoc\FatturaPA\DatiVeicoli;
use App\DataMapper\EDoc\FatturaPA\Body\DatiGenerali;
use Spatie\LaravelData\Attributes\Validation\Max;

class Allegati extends Data
{
    //string 60
    #[Max(60)]
    public string $NomeAttachment;

    //base64 binary
    public mixed $Attachment;

    //string 10
    #[Max(10)]
    public string|Optional $AlgoritmoCompressione;

    //string 10
    #[Max(10)]
    public string|Optional $FormatoAttachment;

    //string 100
    #[Max(100)]
    public string|Optional $DescrizioneAttachment;  
}