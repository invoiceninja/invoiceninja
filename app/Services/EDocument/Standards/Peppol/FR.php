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
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;

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

        if ($classification === 'government') {
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

        // FR sender, FR receiver (domestic)
        if ($invoice->client->country->iso_3166_2 == 'FR') {

            // B2G: Route to Chorus Pro
            if ($invoice->client->classification == 'government') {
                $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                    ["scheme" => 'FR:SIRET', "id" => '11000201100044'],
                ]));

                $mutator_util->setCustomerAssignedAccountId(true);

                return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
            }

            // B2B: Route via SIRENE (9-digit) or SIRET (14-digit)
            if (in_array($invoice->client->classification, ['business'])) {
                $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                    ["scheme" => $this->resolveClientScheme($invoice), "id" => $invoice->client->id_number],
                ]));

                return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
            }

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // FR sender, non-FR receiver (cross-border)
        $code = (new StorecoveRouter())->setInvoice($invoice)->resolveRouting($invoice->client->country->iso_3166_2, $invoice->client->classification);

        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => $code, "id" => $invoice->client->vat_number],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in France but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // non-FR sender, FR receiver, B2G
        if ($invoice->client->classification == 'government') {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => '11000201100044'],
            ]));

            $mutator_util->setCustomerAssignedAccountId(true);

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // non-FR sender, FR receiver, B2B
        if (in_array($invoice->client->classification, ['business'])) {
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => $this->resolveClientScheme($invoice), "id" => $invoice->client->id_number],
            ]));
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Resolve FR:SIRENE (9-digit) or FR:SIRET (14-digit) based on client id_number length.
     */
    private function resolveClientScheme(mixed $invoice): string
    {
        return strlen($invoice->client->id_number ?? '') == 9 ? 'FR:SIRENE' : 'FR:SIRET';
    }
}
