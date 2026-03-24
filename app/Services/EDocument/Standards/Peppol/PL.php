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
 * Poland - KSeF network
 *
 * Not yet mandatory. To force use:
 * - Provide PL:VAT routing identifier
 * - Specify "pl-ksef" network with enabled=true
 * - LegalEntity must be setup for this network
 */
class PL extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["G+B", "", "PL:VAT", "PL:VAT"];
    }
}
