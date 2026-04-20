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

    public function getCandidates(object $client, string $classification, object $router): array
    {
        if ($classification === 'government') {
            $id = preg_replace("/[^a-zA-Z0-9]/", "", $client->routing_id ?? '');
            return strlen($id) >= 2 ? [['scheme' => 'DE:LWID', 'id' => $id]] : [];
        }

        if ($classification === 'individual') {
            $id = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
            return strlen($id) >= 2 ? [['scheme' => 'DE:STNR', 'id' => $id]] : [];
        }

        // Business: default VAT
        $id = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        return strlen($id) >= 2 ? [['scheme' => 'DE:VAT', 'id' => $id]] : [];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        $mutator_util->setPaymentMeans(true);

        return $p_invoice;
    }
}
