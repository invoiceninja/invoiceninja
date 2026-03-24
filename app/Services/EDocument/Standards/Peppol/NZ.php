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
 * New Zealand
 *
 * Uses a GLN to identify businesses. When sending to NZ customers,
 * include the pseudo identifier NZ:GST as their tax identifier.
 */
class NZ extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B+G", "GLN", "NZ:GST", "GLN"];
    }
}
