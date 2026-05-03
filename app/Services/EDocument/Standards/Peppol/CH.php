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
 * Switzerland - QR-Bill to be implemented at a later date.
 */
class CH extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B+G", "CH:UIDB", "CH:VAT", "CH:UIDB"];
    }
}
