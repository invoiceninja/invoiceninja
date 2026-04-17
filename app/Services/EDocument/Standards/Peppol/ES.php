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

class ES extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return ["B", "", "ES:VAT", "ES:VAT"];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        if (!isset($invoice->due_date)) {
            $p_invoice->DueDate = new \DateTime($invoice->date);
        }

        if ($invoice->client->classification == 'business' && $invoice->company->getSetting('classification') == 'business') {
            // B2B requires payment means as credit_transfer
            $mutator_util->setPaymentMeans(true);
        }

        // For B2G, provide three ES:FACE identifiers in the routing object,
        // as well as the ES:VAT tax identifier in the accountingCustomerParty.publicIdentifiers.
        // The invoice will then be routed through the FACe network.
        // The three required ES:FACE identifiers are:
        //   ES-01-FISCAL, ES-02-RECEPTOR, ES-03-PAGADOR

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
