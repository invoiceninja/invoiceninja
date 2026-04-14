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

use App\Services\EDocument\Gateway\MutatorUtil;

class DE extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "DE:LWID", false, "DE:LWID"],
            ["B", "", "DE:VAT", "DE:VAT"],
        ];
    }

    public function resolveRoutingOverride(?string $classification, ?object $invoice = null): ?string
    {
        if ($classification === 'individual') {
            return 'DE:STNR';
        }

        return null;
    }

    public function resolveTaxSchemeOverride(?string $classification, ?object $invoice = null): ?string
    {
        if ($classification === 'individual') {
            return 'DE:STNR';
        }

        return null;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        $mutator_util->setPaymentMeans(true);

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * DE government uses routing_id (Leitweg-ID) for routing.
     */
    public function resolveIdentifier(string $scheme, object $client): ?string
    {
        if (($client->classification ?? 'business') === 'government') {
            return $client->routing_id ?? null;
        }

        return null;
    }
}
