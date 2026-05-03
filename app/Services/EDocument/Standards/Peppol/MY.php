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
 * Malaysia - complex implementation, delayed.
 */
class MY extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B", "MY:EIF", "MY:TIN", "MY:EIF"];
    }
}
