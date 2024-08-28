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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Models\Company;
use Illuminate\Support\Facades\Http;

enum HttpVerb: string
{
    case POST = 'post';
    case PUT = 'put';
    case GET = 'get';
    case PATCH = 'patch';
    case DELETE = 'delete';
}

class Storecove
{
    private string $base_url = 'https://api.storecove.com/api/v2/';

    private array $peppol_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "peppol",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "de:lwid",
        "identifier" => "DE:VAT"
    ];

    private array $dbn_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "dbnalliance",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "gln",
        "identifier" => "1200109963131"
    ];


    public function __construct()
    {
    }

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

        $uri =  "api/v2/discovery/receives";

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
     *
     *
        // documentType : invoice/invoice_response/order
        // rawDocumentData : {
        // document: base64_encode($ubl)
        // parse: true
        // parseStrategy: ubl
        // }
     */
    public function sendJsonDocument($document)
    {

        $payload = [
            "legalEntityId" => 290868,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            "routing" => [
                "eIdentifiers" => [],
                "emails" => ["david@invoiceninja.com"]
            ],
            // "document" => [
            //     'documentType' => 'invoice',
            //     "rawDocumentData" => [
            //         "document" => base64_encode($document),
            //         "parse" => true,
            //         "parseStrategy" => "ubl",
            //     ],
            // ],
            "document" => [
                "documentType" => "invoice",
            "invoice" => $document,
            ],
        ];

        $uri = "document_submissions";

        nlog($payload);

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        nlog($r->body());
        nlog($r->json());

        if($r->successful()) {
            return $r->json()['guid'];
        }

        return false;

    }

    public function sendDocument(string $document, int $routing_id, array $override_payload = [])
    {

        $payload = [
            "legalEntityId" => $routing_id,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            "routing" => [
                "eIdentifiers" => [],
                "emails" => ["david@invoiceninja.com"]
            ],
            "document" => [

            ],
        ];

        $payload = array_merge($payload, $override_payload);


        $payload['document']['documentType'] = 'invoice';
        $payload['document']["rawDocumentData"] = [
                    "document" => base64_encode($document),
                    "parse" => true,
                    "parseStrategy" => "ubl",
        ];

        $uri = "document_submissions";

        nlog($payload);

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        nlog($r->body());
        nlog($r->json());

        if($r->successful()) {
            return $r->json()['guid'];
        }

        return false;

    }

    //document submission sending evidence
    public function getSendingEvidence(string $guid)
    {
        $uri = "document_submissions/{$guid}";
        $r = $this->httpClient($uri, (HttpVerb::GET)->value, [], $this->getHeaders());

    }

    // {
    // "party_name": "<string>",
    // "line1": "<string>",
    // "city": "<string>",
    // "zip": "<string>",
    // "country": "EH",
    // "line2": "<string>",
    // "county": "<string>",
    // "tenant_id": "<string>",
    // "public": true,
    // "advertisements": [
    //     "invoice"
    // ],
    // "third_party_username": "<string>",
    // "third_party_password": "<string>",
    // "rea": {
    //     "province": "AR",
    //     "identifier": "<string>",
    //     "capital": "<number>",
    //     "partners": "SM",
    //     "liquidation_status": "LN"
    // },
    // "acts_as_sender": true,
    // "acts_as_receiver": true,
    // "tax_registered": true
    // }

    // acts_as_receiver - optional - Default : true
    // acts_as_sender - optional - Default : true
    // advertisements - optional < enum (invoice, invoice_response, order, ordering, order_response, selfbilling) > array
    // city - required - Length : 2 - 64
    // country - required - ISO 3166-1 alpha-2
    // county - optional - Maximal length : 64
    // line1 - required - The first address line - Length : 2 - 192
    // line2 - optional - The second address line, if applicable Maximal length : 192
    // party_name - required - The name of the company. Length : 2 - 64
    // public - optional - Whether or not this LegalEntity is public. Public means it will be entered into the PEPPOL directory at https://directory.peppol.eu/ Default : true
    // rea - optional - The REA details for the LegalEntity. Only applies to IT (Italian) LegalEntities. - https://www.storecove.com/docs/#_openapi_rea (schema)

    // capital - optional - The captial for the company. - number
    // identifier - optional - The identifier. Length : 2 - 20
    // liquidation_status - optional - The liquidation status of the company. enum (LN, LS)
    // partners - optional - The number of partners. enum (SU, SM)
    // province - optional - The provincia of the ufficio that issued the identifier.enum (AG, AL, AN, AO, AQ, AR, AP, AT, AV, BA, BT, BL, BN, BG, BI, BO, BZ, BS, BR, CA, CL, CB, CI, CE, CT, CZ, CH, CO, CS, CR, KR, CN, EN, FM, FE, FI, FG, FC, FR, GE, GO, GR, IM, IS, SP, LT, LE, LC, LI, LO, LU, MC, MN, MS, MT, VS, ME, MI, MO, MB, NA, NO, NU, OG, OT, OR, PD, PA, PR, PV, PG, PU, PE, PC, PI, PT, PN, PZ, PO, RG, RA, RC, RE, RI, RN, RO, SA, SS, SV, SI, SR, SO, TA, TE, TR, TO, TP, TN, TV, TS, UD, VA, VE, VB, VC, VR, VV, VI, VT)

    // tax_registered - optional - Whether or not this LegalEntity is tax registered. This influences the validation of the data presented when sending documents. Default : true
    // tenant_id - optional - The id of the tenant, to be used in case of single-tenant solutions that share webhook URLs. This property will included in webhook events. Maximal length : 64
    // third_party_password - optional - The password to use to authenticate to a system through which to send the document, or to obtain tax authority approval to send it. This field is currently relevant only for India and mandatory when creating an IN LegalEntity. Length : 2 - 64
    // third_party_username - optional - The username to use to authenticate to a system through which to send the document, or to obtain tax authority approval to send it. This field is currently relevant only for India and mandatory when creating an IN LegalEntity. Length : 2 - 64
    // zip - required - The zipcode. Length : 2 - 32

    /**
     * CreateLegalEntity
     *
     * @url https://www.storecove.com/docs/#_openapi_legalentitycreate
     * @return mixed
     */
    public function createLegalEntity(array $data, Company $company)
    {
        $uri = 'legal_entities';

        $company_defaults = [
            'acts_as_receiver' => true,
            'acts_as_sender' => true,
            'advertisements' => ['invoice'],
            'city' => $company->settings->city,
            'country' => $company->country()->iso_3166_2,
            'county' => $company->settings->state,
            'line1' => $company->settings->address1,
            'line2' => $company->settings->address2,
            'party_name' => $company->settings->name,
            'tax_registered' => true,
            'tenant_id' => $company->company_key,
            'zip' => $company->settings->postal_code,
        ];

        $payload = array_merge($company_defaults, $data);

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }

    public function getLegalEntity($id)
    {

        $uri = "legal_entities/{$id}";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, []);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }

    public function updateLegalEntity($id, array $data)
    {

        $uri = "legal_entities/{$id}";

        $r = $this->httpClient($uri, (HttpVerb::PATCH)->value, $data);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }

    public function addIdentifier(int $legal_entity_id, string $identifier, string $scheme)
    {
        $uri = "legal_entities/{$legal_entity_id}/peppol_identifiers";

        $data = [
            "identifier" => $identifier,
            "scheme" => $scheme,
            "superscheme" => "iso6523-actorid-upis",
        ];

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $data);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function getHeaders(array $headers = [])
    {

        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
        ], $headers);

    }

    private function httpClient(string $uri, string $verb, array $data, ?array $headers = [])
    {

        $r = Http::withToken(config('ninja.storecove_api_key'))
                ->withHeaders($this->getHeaders($headers))
                ->{$verb}("{$this->base_url}{$uri}", $data);

        return $r;
    }

}
