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

use App\Models\Client;
use App\Services\EDocument\Gateway\MutatorUtil;

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

    public function getNetworkOverrides(?Client $client = null): array
    {
        if ($client && $client->company->country()->iso_3166_2 !== 'PL') {
            return [];
        }

        return [['application' => 'pl-ksef', 'settings' => ['enabled' => true]]];
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        // PL sender, PL receiver (domestic) — resolve voivodeship
        if ($invoice->client->country->iso_3166_2 == 'PL') {
            $client_state = $mutator_util->getClientSetting('Invoice.AccountingCustomerParty.Party.PostalAddress.Address.CountrySubentity');
            $resolved_state = $this->getStateCode($client_state, $invoice);
            $p_invoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity = $resolved_state;
        }

        return $p_invoice;
    }

    /**
     * Receiver mutations for when the client is in Poland but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
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
