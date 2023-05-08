<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Tax;

use App\Models\Client;
use App\Models\Company;
use App\Services\Tax\Providers\ZipTax;


class TaxService
{

    public function __construct(public Client $client)
    {
    }

    public function validateVat(): self
    {
        $client_country_code = $this->client->shipping_country ? $this->client->shipping_country->iso_3166_2 : $this->client->country->iso_3166_2;

        $vat_check = (new VatNumberCheck($this->client->vat_number, $client_country_code))->run();

        $this->client->has_valid_vat_number = $vat_check->isValid();
        $this->client->saveQuietly();

        return $this;
    }
}