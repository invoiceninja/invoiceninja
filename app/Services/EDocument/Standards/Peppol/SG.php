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
 * Singapore - CorpPass / InvoiceNow
 *
 * Delayed - stage 2 implementation.
 * Uses SG:UEN with CorpPass OAuth flow for registration.
 */
class SG extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "SG:UEN", false, "0195:SGUENT08GA0028A"],
            ["B", "SG:UEN", "SG:GST", "SG:UEN"],
        ];
    }
}
