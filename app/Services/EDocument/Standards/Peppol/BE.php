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
        $stripped = preg_replace("/^BE/i", "", $vat);

        if (strlen($stripped) < 2) {
            return [];
        }

        return [
            ['scheme' => 'BE:EN', 'id' => $stripped],
            ['scheme' => 'BE:VAT', 'id' => 'BE' . $stripped],
        ];
    }
}
