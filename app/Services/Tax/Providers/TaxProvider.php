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

namespace App\Services\Tax\Providers;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TaxProvider
{

    public array $eu_countries = [
        "AT",
        "BE",
        "BG",
        "HR",
        "CY",
        "CZ",
        "DK",
        "EE",
        "FI",
        "FR",
        "DE",
        "GR",
        "HU",
        "IE",
        "IT",
        "LV",
        "LT",
        "LU",
        "MT",
        "NL",
        "PL",
        "PT",
        "RO",
        "SK",
        "SI",
        "ES",
        "SE"
    ];

    private string $provider = ZipTax::class;
    
    private mixed $api_credentials;


    public function __construct(protected Company $company, protected Client $client)
    {

    }


    public function updateCompanyTaxData(): self
    {
        $this->configureProvider($this->provider); //hard coded for now to one provider, but we'll be able to swap these out later

        $company_details = [
            'address1' => $this->company->settings->address1,
            'address2' => $this->company->settings->address2,
            'city' => $this->company->settings->city,
            'state' => $this->company->settings->state,
            'postal_code' => $this->company->settings->postal_code,
            'country_id' => $this->company->settings->country_id,
        ];

        $tax_provider = new $this->provider($company_details);

        $tax_provider->setApiCredentials($this->api_credentials);
        
        $tax_data = $tax_provider->run();
        
        $this->company->tax_data = $tax_data;
        
        $this->company->save();

        return $this;

    }

    public function updateClientTaxData(): self
    {
        $this->configureProvider($this->provider); //hard coded for now to one provider, but we'll be able to swap these out later

        $billing_details =[
            'address1' => $this->client->address1,
            'address2' => $this->client->address2,
            'city' => $this->client->city,
            'state' => $this->client->state,
            'postal_code' => $this->client->postal_code,
            'country_id' => $this->client->country_id,
        ];

        $shipping_details =[
            'address1' => $this->client->shipping_address1,
            'address2' => $this->client->shipping_address2,
            'city' => $this->client->shipping_city,
            'state' => $this->client->shipping_state,
            'postal_code' => $this->client->shipping_postal_code,
            'country_id' => $this->client->shipping_country_id,
        ];


        $tax_provider = new $this->provider();

        $tax_provider->setApiCredentials($this->api_credentials);
        
        $tax_data = $tax_provider->run();
        
        $this->company->tax_data = $tax_data;
        
        $this->company->save();

        return $this;


        return $this;

    }

    private function configureProvider(?string $provider): self
    {

        match($this->client->country->iso_3166_2){
            'US' => $this->configureZipTax(),
            "AT" => $this->configureEuTax(),
            "BE" => $this->configureEuTax(),
            "BG" => $this->configureEuTax(),
            "HR" => $this->configureEuTax(),
            "CY" => $this->configureEuTax(),
            "CZ" => $this->configureEuTax(),
            "DK" => $this->configureEuTax(),
            "EE" => $this->configureEuTax(),
            "FI" => $this->configureEuTax(),
            "FR" => $this->configureEuTax(),
            "DE" => $this->configureEuTax(),
            "GR" => $this->configureEuTax(),
            "HU" => $this->configureEuTax(),
            "IE" => $this->configureEuTax(),
            "IT" => $this->configureEuTax(),
            "LV" => $this->configureEuTax(),
            "LT" => $this->configureEuTax(),
            "LU" => $this->configureEuTax(),
            "MT" => $this->configureEuTax(),
            "NL" => $this->configureEuTax(),
            "PL" => $this->configureEuTax(),
            "PT" => $this->configureEuTax(),
            "RO" => $this->configureEuTax(),
            "SK" => $this->configureEuTax(),
            "SI" => $this->configureEuTax(),
            "ES" => $this->configureEuTax(),
            "SE" => $this->configureEuTax(),
            default => $this->noTaxRegionDefined(),
        };

        return $this;

    }

    private function configureEuTax(): self
    {
        $this->provider = EuTax::class;

        // $this->api_credentials = config('services.tax.eu_tax.key');

        return $this;
    }

    private function noTaxRegionDefined(): self
    {
        return $this;
    }

    private function configureZipTax(): self
    {

        $this->provider = ZipTax::class;
        
        $this->api_credentials = config('services.tax.zip_tax.key');

        return $this;

    }

}