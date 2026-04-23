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

class AT extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "AT:GOV", false, "9915:b"],
            ["B", "", "AT:VAT", "AT:VAT"],
        ];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        if ($invoice->client->classification == 'government') {
            // Route to AT:GOV - "b" for production, "test" for test environment
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting(["scheme" => 'AT:GOV', "id" => 'b']));

            // For government clients, customerAssignedAccountId must be set
            $mutator_util->setCustomerAssignedAccountId(true);
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
