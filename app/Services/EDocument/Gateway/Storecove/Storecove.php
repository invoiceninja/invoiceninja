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

namespace App\Services\EDocument\Gateway;

use Illuminate\Support\Facades\Http;


enum HttpVerb: string
{
    case POST = 'post';
    case PUT = 'put';
    case GET = 'get';
    case PATCH = 'patch';
    case DELETE = 'delete';
}

class Storecove {

    private array $peppol_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "peppol",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "de:lwid",
        "identifier" => "10101010-STO-10"
    ];

    private array $dbn_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "dbnalliance",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "gln",
        "identifier" => "1200109963131"
    ];
    

    public function __construct(){}

    //config('ninja.storecove_api_key');


    //https://app.storecove.com/en/docs#_test_identifiers
    //check if identifier is able to send on the network.


    //response = {  "code": "OK",  "email": false}
    public function discovery($identifier, $scheme, $network = 'peppol')
    {
        $network_data = [];

        match ($network) {
            'peppol' => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            'dbn' => $network_data = array_merge($this->dbn_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            default => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
        };

        $uri =  "https://api.storecove.com/api/v2/discovery/receives";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $network_data, $this->getHeaders());

        return ($r->successful() && $r->json()['code'] == 'OK') ? true : false;
            
    }

    //response = "guid" : "xx",

    /**
     * If the receiver cannot be found, then an
     * email is sent to that user if a appropriate 
     * email is included in the document payload
     * 
     * {
            "routing":  {
                "emails": [
                "test@example.com"
                ],
                "eIdentifiers": []
            }
        }
     *
     */
    public function sendDocument($document)
    {
        $uri = "https://api.storecove.com/api/v2/document_submissions";
        
        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $document, $this->getHeaders());

        if($r->successful())
            return $r->json()['guid'];

        return false;

    }

    //document submission sending evidence
    public function sendingEvidence(string $guid)
    {
        $uri = "https://api.storecove.com/api/v2/document_submissions/{$guid}";
        $r = $this->httpClient($uri, (HttpVerb::GET)->value, [], $this->getHeaders());

    }

    private function getHeaders(array $headers = [])
    {

        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
        ], $headers);

    }

    public function httpClient(string $uri, string $verb, array $data, ?array $headers = [])
    {

        $r = Http::withToken(config('ninja.storecove_api_key'))
                ->withHeaders($this->getHeaders($headers))
                ->{$verb}($uri, $data);

        return $r;
    }
    
//     curl \
// -X POST  \
// -H "Accept: application/json" \
// -H "Authorization: Bearer API_KEY_HERE" \
// -H "Content-Type: application/json" \
// --data @discovery.json
}