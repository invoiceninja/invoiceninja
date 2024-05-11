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

namespace App\DataMapper\EDoc\FatturaPA\Body;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\Validation\Regex;

class DatiBollo extends Data
{

//SI
#[Size(2)]
public string $BolloVirtuale = 'SI';

//Amount2DecimalType
#[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2}$/')]
public float|Optional $ImportoBollo;

}