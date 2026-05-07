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

    public function resolveClientEndpointCandidate(object $client, object $invoice, StorecoveRouter $router): array
    {

        $vat_number = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');

        if(strlen($vat_number ?? '') > 1){

            return [
                ['scheme' => 'BE:EN', 'id' => str_ireplace('BE', '', $vat_number)],
                ['scheme' => 'BE:VAT', 'id' => $vat_number],
            ];
        }
        elseif(strlen($client->id_number ?? '') > 1){

            $en_number = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
            $en_number = str_ireplace('BE', '', $en_number);

            return [
                ['scheme' => 'BE:EN', 'id' => $en_number],
            ];
        }

        return [];
    }

    /**
     * resolveCompanyScheme
     * 
     * The base case is that we always return the companys VAT and a generic ICD code 
     * 
     * @param Company $company
     * @return array
     */
    public function resolveEndpointScheme(Company $company): array
    {
        /** Prioritize GLN if Present */
        if(stripos($company->settings->id_number ?? '', '0088:') !== false){

            return [
                'scheme' => '0088',
                'id' => str_replace('0088:', '', $company->settings->id_number),    
            ];

        }

        // Fallback to VAT => ID Number.
        $endpoint_id = strlen($company->settings->vat_number) > 1 ? $company->settings->vat_number : $company->settings->id_number ?? '';
        $endpoint_id = preg_replace("/[^a-zA-Z0-9]/", "", $endpoint_id);
        $endpoint_id = preg_replace('/^BE/i', '', $endpoint_id);

        return [
            'scheme' => '0208',
            'id' => $endpoint_id
        ];
    }

    public function resolvePartyIdentificationScheme(Company $company): ?array
    {
        return $this->resolveEndpointScheme($company);
    }
}
