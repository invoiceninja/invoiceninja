<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Peppol;

/**
 * Australia - if payment means are included, they must be the same type.
 */
class AU extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B+G", "AU:ABN", "AU:ABN", "AU:ABN"];
    }
}
