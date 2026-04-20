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

/**
 * France — Chorus Pro (B2G) + PEPPOL (B2B)
 *
 * B2G: All government invoices route to Chorus Pro via SIRET 0009:11000201100044.
 *       The final recipient's SIRET must be included as customerAssignedAccountId.
 * B2B: Route via FR:SIRENE (9-digit) or FR:SIRET (14-digit) based on client id_number.
 * B2C: Out of scope — France's e-invoicing mandate covers B2B/B2G only.
 */
class FR extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "FR:SIRET + customerAssignedAccountIdValue", false, "0009:11000201100044"],
            ["B", "FR:SIRENE or FR:SIRET", "FR:VAT", "FR:SIRENE or FR:SIRET"],
        ];
    }

    public function getCandidates(object $client, string $classification, object $router): array
    {
        if ($classification === 'government') {
            return [['scheme' => '0009', 'id' => '11000201100044']];
        }

        $idNumber = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        if (strlen($idNumber) < 9) {
            return [];
        }

        $scheme = strlen($idNumber) === 9 ? 'FR:SIRENE' : 'FR:SIRET';
        return [['scheme' => $scheme, 'id' => $idNumber]];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        // FR sender, FR receiver (domestic), B2G: customerAssignedAccountId required
        if ($invoice->client->country->iso_3166_2 == 'FR' && $invoice->client->classification == 'government') {
            $mutator_util->setCustomerAssignedAccountId(true);
        }

        return $p_invoice;
    }

    /**
     * Receiver mutations for when the client is in France but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        // non-FR sender, FR receiver, B2G: customerAssignedAccountId required
        if ($invoice->client->classification == 'government') {
            $mutator_util->setCustomerAssignedAccountId(true);
        }

        return $p_invoice;
    }

}
