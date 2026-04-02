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

class FR extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "FR:SIRET + customerAssignedAccountIdValue", false, "0009:11000201100044"],
            ["B", "FR:SIRENE or FR:SIRET", "FR:VAT", "FR:SIRENE or FR:SIRET"],
        ];
    }

    public function resolveRoutingOverride(?string $classification, ?object $invoice = null): ?string
    {
        if (!$invoice) {
            return null;
        }

        $code = match ($classification) {
            'government' => 'G',
            'individual' => 'C',
            default => 'B',
        };

        if ($code === 'B' && strlen($invoice->client->id_number) == 9) {
            return 'FR:SIRENE';
        } elseif ($code === 'B' && strlen($invoice->client->id_number) == 14) {
            return 'FR:SIRET';
        } elseif ($code === 'G') {
            return '0009:11000201100044';
        }

        return null;
    }

    public function resolveTaxSchemeOverride(?string $classification, ?object $invoice = null): ?string
    {
        if (!$invoice) {
            return null;
        }

        $code = match ($classification) {
            'government' => 'G',
            'individual' => 'C',
            default => 'B',
        };

        if ($code === 'G') {
            return '0009:11000201100044';
        }

        return null;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // When sending invoices to the French government (Chorus Pro):
        // All invoices have to be routed to SIRET 0009:11000201100044.
        // There is no test environment for sending to public entities.
        if ($invoice->client->classification == 'government') {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => '11000201100044'],
            ]));

            // The SIRET / 0009 identifier of the final recipient is to be included
            // in the invoice.accountingCustomerParty.publicIdentifiers array.
            $mutator_util->setCustomerAssignedAccountId(true);
        }

        if (strlen($invoice->client->id_number ?? '') == 9) {
            // SIREN
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => "{$invoice->client->id_number}"],
            ]));
        } else {
            // SIRET
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => "{$invoice->client->id_number}"],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
