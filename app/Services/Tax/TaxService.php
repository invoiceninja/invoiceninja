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

namespace App\Services\Tax;

use App\Models\Client;

class TaxService
{
    public function __construct(public Client $client) {}

    public function validateVat(): self
    {
        $client_country_code = $this->client->shipping_country ? $this->client->shipping_country->iso_3166_2 : $this->client->country->iso_3166_2;

        $vat_check = (new VatNumberCheck($this->client->vat_number, $client_country_code))->run();

        // nlog($vat_check);

        // Written either way, so that a re-check can also take the reverse charge away.
        // When VIES gives no verdict run() throws, and the flag is left as it was.
        $this->client->has_valid_vat_number = $vat_check->isValid();

        if ($vat_check->isValid()) {

            if (!$this->client->name && strlen($vat_check->getName()) > 2) {
                $this->client->name = $vat_check->getName();
            }

            if (empty($this->client->private_notes) && strlen($vat_check->getAddress()) > 2) {
                $this->client->private_notes = $vat_check->getAddress();
            }
        }

        $this->client->saveQuietly();

        return $this;

    }

    public function initTaxProvider() {}
}
