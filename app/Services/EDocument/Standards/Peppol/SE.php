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

/**
 * Sweden - Svefaktura
 *
 * Routing through SE:ORGNR together with a network specification.
 * Can also use SE:OPID operator id.
 */
class SE extends BaseCountry
{
    /**
     * Enable Svefaktura network for Swedish receivers.
     */
    public function getNetworkOverrides(?Client $client = null): array
    {
        if ($client && $client->country?->iso_3166_2 !== 'SE') {
            return [];
        }

        return [[
            'application' => 'svefaktura',
            'settings' => ['enabled' => true],
        ]];
    }
}
