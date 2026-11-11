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
use App\Models\Company;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;

class BE extends BaseCountry
{
    /**
     * Register both BE:VAT and BE:EN identifiers for HERMES network support.
     */
    public function getAdditionalIdentifiers(array $data): array
    {
        $identifier = str_replace([' ', 'BE'], '', $data['vat_number']);

        return [
            ['identifier' => $identifier, 'scheme' => 'BE:EN'],
        ];
    }

    /**
     * Belgium dual-scheme discovery cascade.
     *
     * This methods determines the routing candidates for a client ordered in preference.
     * 
     * Belgium supports both BE:EN (Enterprise Number via HERMES) and BE:VAT.
     * Try BE:EN first (stripped of country prefix), then BE:VAT (with prefix).
     */
    public function getCandidates(object $client, string $classification, object $router): array
    {
        $vat = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        $fromId = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        $raw = strlen($vat) >= 2 ? $vat : $fromId;
        $stripped = preg_replace("/^BE/i", "", $raw);

        if (strlen($stripped) < 2) {
            return [];
        }

        return [
            ['scheme' => 'BE:EN', 'id' => $stripped],
            ['scheme' => 'BE:VAT', 'id' => 'BE' . $stripped],
        ];
    }

    /**
     * Belgium supplier `cbc:EndpointID` resolution.
     *
     * Always emits the BE:EN ICD `0208` (Belgian Enterprise Number). The
     * leading "BE" country prefix is stripped because `0208` requires the
     * 10-digit enterprise number without the country code (per
     * PEPPOL-COMMON-R043).
     *
     * @param Company $company
     * @return array{scheme: string, id: string}
     */
    public function resolveEndpointScheme(Company $company): array
    {
        if ($gln = $this->glnEndpointFromIdentifier($company->settings->id_number ?? null)) {
            return $gln;
        }

        $endpoint_id = preg_replace('/^BE/i', '', $this->rawCompanyEndpointValue($company));

        return [
            'scheme' => '0208',
            'id' => $endpoint_id,
        ];
    }

    public function resolvePartyIdentificationScheme(Company $company): ?array
    {
        return $this->resolveEndpointScheme($company);
    }

    /**
     * Mirror the buyer's EndpointID into `cac:PartyIdentification` for BE
     * clients (same `0208` Enterprise Number), keeping consistency with the
     * supplier-side BE behaviour.
     *
     * @param  Client $client
     * @return array{scheme: string, id: string}|null
     */
    public function resolveClientPartyIdentificationScheme(Client $client): ?array
    {
        $vat = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        $fromId = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        $raw = strlen($vat) >= 2 ? $vat : $fromId;
        $stripped = preg_replace("/^BE/i", "", $raw);

        if (strlen($stripped) < 2) {
            return null;
        }

        return [
            'scheme' => '0208',
            'id' => $stripped,
        ];
    }
}
