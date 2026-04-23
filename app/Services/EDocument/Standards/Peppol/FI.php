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
 * Finland - For Finvoice, provide an FI:OPID routing identifier and an FI:OVT legal identifier.
 * An FI:VAT is recommended.
 */
class FI extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B+G", "FI:OVT", "FI:VAT", "FI:OVT"];
    }
}
