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

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ZipTax implements TaxProviderInterface
{

    private string $endpoint = 'https://api.zip-tax.com/request/v40';

    private string $api_key = '';

    public function __construct(protected array $address)
    {
    }

    public function run()
    {
        $string_address = implode(" ", $this->address);

        $response = $this->callApi(['key' => $this->api_key, 'address' => $string_address]);

        if($response->successful()){
            
            return $this->parseResponse($response->json());

        }

        if(isset($this->address['postal_code'])) {
           $response = $this->callApi(['key' => $this->api_key, 'address' => $this->address['postal_code']]);

            if($response->successful())
                return $this->parseResponse($response->json());

        }

        // $response->throw();

    }
    
    public function setApiCredentials($api_key): self
    {
        $this->api_key = $api_key;

        return $this;
    }

    /**
     * callApi
     *
     * @param  array $parameters
     * @return Response
     */
    private function callApi(array $parameters): Response
    {
        $response = Http::retry(3, 1000)->withHeaders([])->get($this->endpoint, $parameters);

        return $response;

    }

    private function parseResponse($response)
    {
        if(isset($response['results']['0']))
            return $response['results']['0'];

        return null;
        // throw new \Exception("Error resolving tax  (code) = " . $response['rCode']);
    }
}
