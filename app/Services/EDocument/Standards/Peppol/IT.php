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

class IT extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "", "IT:IVA", "IT:CUUO"],
            ["B", "", "IT:IVA", "IT:CUUO"],
            ["C", "", "IT:CF", "Email"],
        ];
    }

    public function getCandidates(object $client, string $classification, object $router): array
    {
        if ($classification === 'individual') {
            $cf = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
            return strlen($cf) >= 2 ? [['scheme' => 'IT:CF', 'id' => $cf]] : [];
        }

        // B2B/B2G: CUUO (routing_id) for SDI delivery + IVA for identification
        $candidates = [];
        $routingId = $client->routing_id ?? '';
        if (strlen($routingId) >= 2) {
            $candidates[] = ['scheme' => 'IT:CUUO', 'id' => $routingId];
        }
        $vat = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        if (strlen($vat) >= 2) {
            $candidates[] = ['scheme' => 'IT:IVA', 'id' => $vat];
        }
        return $candidates;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
    }

    /**
     * Receiver mutations for when the client is in Italy but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
    }
}
