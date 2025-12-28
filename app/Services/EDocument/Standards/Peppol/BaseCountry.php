<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Peppol;

use App\Models\Invoice;
use App\Services\EDocument\Standards\Peppol;

class BaseCountry
{
    public function __construct(protected Invoice $invoice)
    {
    }

}
