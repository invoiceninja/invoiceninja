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

namespace App\DataMapper\EDoc\FatturaPA\Header;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;

class IdFiscaleIVA extends Data
{
    public function __construct(
    // #[\Required]
    #[Regex('/^[A-Z]{2}$/')]
    public string $IdPaese = '',

    // #[\Required]
    #[Min(1)]
    #[Max(28)]
    public string $IdCodice = '',
    ){}
}
