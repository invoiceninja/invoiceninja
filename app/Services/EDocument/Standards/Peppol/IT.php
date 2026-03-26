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

class IT extends BaseCountry
{
    public function getRoutingRules(): ?array
    {
        return [
            ["G", "", "IT:IVA", "IT:CUUO"],
            ["B", "", "IT:IVA", "IT:CUUO"],
            ["C", "", "IT:CF", "Email"],
            ["G", "", "IT:IVA", "IT:CUUO"],
        ];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)
        if (in_array($invoice->client->classification, ['business', 'government']) && $invoice->company->country()->iso_3166_2 == 'IT') {

            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'IT:IVA', "id" => $invoice->client->vat_number],
                ["scheme" => 'IT:CUUO', "id" => $invoice->client->routing_id],
            ]));

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and email routing
        if ($invoice->client->classification == 'individual' && $invoice->company->country()->iso_3166_2 == 'IT') {

            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'IT:CF', "id" => $invoice->client->vat_number],
            ]));

            $storecove_meta = $this->setEmailRouting($storecove_meta, $invoice->client->present()->email());

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // IT Sender, non-IT Receiver
        // Provide the receiver tax identifier and any routing identifier applicable to the receiving country.
        if ($invoice->client->country->iso_3166_2 != 'IT' && $invoice->company->country()->iso_3166_2 == 'IT') {

            $code = (new StorecoveRouter())->setInvoice($invoice)->resolveRouting($invoice->client->country->iso_3166_2, $invoice->client->classification);

            nlog("foreign receiver");
            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => $code, "id" => $invoice->client->vat_number],
            ]));

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Italy but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // non-IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and an optional email.
        if (in_array($invoice->client->classification, ['individual']) && $invoice->company->country()->iso_3166_2 != 'IT') {
            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // non-IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }
}
