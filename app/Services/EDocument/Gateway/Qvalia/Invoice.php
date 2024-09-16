<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Gateway\Qvalia;

class Invoice
{

    public function __construct(public Qvalia $qvalia)
    {
    }

    // Methods
    public function status(string $legal_entity_id, string $integration_id)
    {
        $uri = "/account/{$legal_entity_id}/action/invoice/outgoing/status/{$integration_id}";

        $r = $this->qvalia->httpClient($uri, (\App\Enum\HttpVerb::GET)->value, []);
        
        return $r->object();
    }

}
