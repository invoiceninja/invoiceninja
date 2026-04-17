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
 * Poland - KSeF (Krajowy System e-Faktur) network.
 *
 * KSeF is Poland's national e-invoicing system.
 * Uses PL:VAT (NIP) as the primary identifier for both B2B and B2G.
 */
class PL extends BaseCountry
{
    public array $countrySubEntity = [
        'PL-DS' => 'Dolnośląskie',
        'PL-KP' => 'Kujawsko-Pomorskie',
        'PL-LU' => 'Lubelskie',
        'PL-LB' => 'Lubuskie',
        'PL-LD' => 'Łódzkie',
        'PL-MA' => 'Małopolskie',
        'PL-MZ' => 'Mazowieckie',
        'PL-OP' => 'Opolskie',
        'PL-PK' => 'Podkarpackie',
        'PL-PD' => 'Podlaskie',
        'PL-PM' => 'Pomorskie',
        'PL-SL' => 'Śląskie',
        'PL-SK' => 'Świętokrzyskie',
        'PL-WN' => 'Warmińsko-Mazurskie',
        'PL-WP' => 'Wielkopolskie',
        'PL-ZP' => 'Zachodniopomorskie',
    ];

    public function getRoutingRules(): ?array
    {
        return ["G+B", "", "PL:VAT", "PL:VAT"];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // Enable KSeF network
        $storecove_meta = $this->mergeMeta($storecove_meta, ["networks" => [
            [
                "application" => "pl-ksef",
                "settings" => [
                    "enabled" => true,
                ],
            ],
        ]]);

        // PL sender, PL receiver (domestic)
        if ($invoice->client->country->iso_3166_2 == 'PL') {

            // B2B / B2G: route via PL:VAT
            if (in_array($invoice->client->classification, ['business', 'government'])) {

                $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                    ["scheme" => 'PL:VAT', "id" => $invoice->client->vat_number],
                ]));

            }

            // B2C: use PL:VAT if available, otherwise email routing
            if ($invoice->client->classification == 'individual') {

                if (strlen($invoice->client->vat_number ?? '') > 1) {
                    $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                        ["scheme" => 'PL:VAT', "id" => $invoice->client->vat_number],
                    ]));
                }

                $storecove_meta = $this->setEmailRouting($storecove_meta, $invoice->client->present()->email());

            }

            // Resolve voivodeship
            $client_state = $mutator_util->getClientSetting('Invoice.AccountingCustomerParty.Party.PostalAddress.Address.CountrySubentity');
            $resolved_state = $this->getStateCode($client_state, $invoice);
            $p_invoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity = $resolved_state;

            return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
        }

        // PL sender, non-PL receiver (cross-border)
        $code = (new StorecoveRouter())->setInvoice($invoice)->resolveRouting($invoice->client->country->iso_3166_2, $invoice->client->classification);

        $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
            ["scheme" => $code, "id" => $invoice->client->vat_number],
        ]));

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Receiver mutations for when the client is in Poland but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {

        // non-PL sender, PL receiver, B2B/B2G
        if (in_array($invoice->client->classification, ['business', 'government'])) {

            $storecove_meta = $this->mergeMeta($storecove_meta, $this->buildRouting([
                ["scheme" => 'PL:VAT', "id" => $invoice->client->vat_number],
            ]));

        }

        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    public function getStateCode(?string $state_code, mixed $invoice = null): string
    {
        $state_code = strlen($state_code ?? '') > 1 ? $state_code : ($invoice ? $invoice->client->state : '');

        if (isset($this->countrySubEntity[$state_code])) {
            return $state_code;
        }

        $key = array_search($state_code, $this->countrySubEntity);

        if ($key !== false) {
            return $key;
        }

        return 'PL-MZ';
    }
}
