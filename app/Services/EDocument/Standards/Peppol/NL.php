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
 * Netherlands
 *
 * When sending to public entities, the invoice.accountingSupplierParty.party.contact.email is mandatory.
 * Dutch senders and receivers require a legal identifier.
 * For companies: NL:KVK, for public entities: NL:OINO.
 */
class NL extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["B", "NL:KVK", "NL:VAT", "NL:VAT"],
            ["G", "NL:OINO", false, "NL:OINO"],
        ];
    }
}
