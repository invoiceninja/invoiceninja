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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use App\Services\EDocument\Gateway\Transformers\StorecoveExpense;

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
    /** @var string $base_url */
    private string $base_url = 'https://api.storecove.com/api/v2/';

    /** @var array $peppol_discovery */
    private array $peppol_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "peppol",
        "metaScheme" =>  "iso6523-actorid-upis",
        // "scheme" =>  "de:lwid",
        // "identifier" => "DE:VAT",
    ];

    /** @var array $dbn_discovery */
    private array $dbn_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "dbnalliance",
        "metaScheme" =>  "iso6523-actorid-upis",
        // "scheme" =>  "gln",
        // "identifier" => "1200109963131",
    ];

    private ?int $legal_entity_id = null;

    public StorecoveRouter $router;

    public Mutator $mutator;

    public StorecoveAdapter $adapter;

    public StorecoveExpense $expense;

    public StorecoveProxy $proxy;

    public StorecoveC5 $c5;

    public LegalEntityService $legalEntity;

    public function __construct()
    {
        $this->router = new StorecoveRouter();
        $this->mutator = new Mutator($this);
        $this->adapter = new StorecoveAdapter($this);
        $this->expense = new StorecoveExpense($this);
        $this->proxy = new StorecoveProxy($this);
        $this->c5 = new StorecoveC5($this);
        $this->legalEntity = new LegalEntityService($this);
    }

    /**
     * build
     *
     * @param  \App\Models\Invoice|\App\Models\Credit $model
     * @return self
     */
    public function build($model): self
    {
        // return
        $this->adapter
             ->transform($model)
             ->decorate();

        return $this;
    }

    public function getResult(): array
    {
        return $this->adapter->getDocument();
    }

    /**
     * Discovery
     *
     * @param  string $identifier
     * @param  string $scheme
     * @param  string $network
     * @return bool
     */
    public function discovery(string $identifier, string $scheme, string $network = 'peppol'): bool
    {
        $network_data = [];

        $network_data = match ($network) {
            'peppol' => [
                ...$this->peppol_discovery,
                'scheme' => $scheme,
                'identifier' => $identifier,
            ],
            'dbn' => array_merge(
                $this->dbn_discovery,
                ['scheme' => $scheme, 'identifier' => $identifier]
            ),
            default => [
                ...$this->peppol_discovery,
                'scheme' => $scheme,
                'identifier' => $identifier,
            ],
        };

        $uri =  "discovery/receives";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $network_data, $this->getHeaders());

        return ($r->successful() && $r->json()['code'] == 'OK') ? true : false;

    }

    /**
     * Discovery - attempts to find the identifier on the network
     *
     * @param  string $identifier
     * @param  string $scheme
     * @param  string $network
     * @return bool
     */
    public function exists(string $identifier, string $scheme, string $network = 'peppol'): bool
    {
        if (stripos($scheme, ' or ') !== false) {
            foreach (array_map('trim', explode(' or ', $scheme)) as $atomicScheme) {
                if ($this->exists($identifier, $atomicScheme, $network)) {
                    return true;
                }
            }

            return false;
        }

        $network_data = [];

        match ($network) {
            'peppol' => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            'dbn' => $network_data = array_merge($this->dbn_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            default => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
        };

        $uri =  "discovery/exists";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $network_data, $this->getHeaders());

        return ($r->successful() && $r->json()['code'] == 'OK') ? true : false;

    }

    /**
     * Submit a document to the Storecove API.
     *
     * @param  array $payload
     * @return string|\Illuminate\Http\Client\Response  GUID on success, Response on failure
     */
    public function sendJsonDocument(array $payload): string|\Illuminate\Http\Client\Response
    {

        $uri = "document_submissions";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        if ($r->successful()) {
            nlog("sent! GUID = {$r->json()['guid']}");
            return $r->json()['guid'];
        }

        return $r;

    }

    /**
     * Send Raw UBL Document via StoreCove
     *
     * @param  string $document
     * @param  int $routing_id
     * @param  array $override_payload
     *
     * @return string|\Illuminate\Http\Client\Response
     */
    public function sendDocument(string $document, int $routing_id, array $override_payload = [])
    {
        $this->legal_entity_id = $routing_id;

        $payload = [
            "legalEntityId" => $routing_id,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            "routing" => [
                "eIdentifiers" => [],
                "emails" => ["peppol@mail.invoicing.co"],
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

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        if ($r->successful()) {
            return $r->json()['guid'];
        }

        return $r;

    }

    /**
     * Get Sending Evidence
     *
     * "guid" => "661c079d-0c2b-4b45-8263-678ed81224af",
     *
     * @param  string $guid
     * @return mixed
     */
    public function getSendingEvidence(string $guid)
    {
        $uri = "document_submissions/{$guid}/evidence";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, [], $this->getHeaders());

        if ($r->successful()) {
            return $r->json();
        }

        return $r;
    }

    /**
     * checkNetworkStatus
     *
     * @param  array $data
     * @return bool|array
     */
    public function checkNetworkStatus(array $data): mixed
    {

        $scheme = $this->router->resolveTaxScheme($data['country'], $data['classification']);

        if (empty($scheme)) {
            return false;
        }

        return (strlen($data['vat_number'] ?? '') > 3 && $this->exists($data['vat_number'], $scheme)) ? [
            'status' => 'error',
            'code' => 422,
            'body' => [],
            'error' => [
                'status' => 'error',
                'code' => 422,
                'message' => 'This VAT number is already registered on the PEPPOL network. Please disconnect if you are using another provider.',
                'errors' => [
                    [
                        'source' => 'identifier',
                        'details' => 'This VAT number is already registered on the PEPPOL network. Please disconnect if you are using another provider.',
                    ],
                ],
            ],
        ] : false;

    }

    /** @deprecated Use $this->legalEntity->setup() directly */
    public function setupLegalEntity(array $data): array|\Illuminate\Http\Client\Response
    {
        return $this->legalEntity->setup($data);
    }


    // ─── Legal entity delegation (implementation in LegalEntityService) ───

    /** @deprecated Use $this->legalEntity->removeIdentifier() */
    public function removePeppolIdentifier(int $legal_entity_id, string $identifier, string $scheme, string $superscheme = "iso6523-actorid-upis"): array|\Illuminate\Http\Client\Response
    {
        return $this->legalEntity->removeIdentifier($legal_entity_id, $identifier, $scheme, $superscheme);
    }

    /** @deprecated Use $this->legalEntity->create() */
    public function createLegalEntity(array $data, ?Company $company = null)
    {
        return $this->legalEntity->create($data, $company);
    }

    /** @deprecated Use $this->legalEntity->get() */
    public function getLegalEntity($id): array|\Illuminate\Http\Client\Response
    {
        return $this->legalEntity->get($id);
    }

    /** @deprecated Use $this->legalEntity->update() */
    public function updateLegalEntity(int $id, array $data)
    {
        return $this->legalEntity->update($id, $data);
    }

    /** @deprecated Use $this->legalEntity->addIdentifier() */
    public function addIdentifier(int $legal_entity_id, string $identifier, string $scheme): array|\Illuminate\Http\Client\Response
    {
        return $this->legalEntity->addIdentifier($legal_entity_id, $identifier, $scheme);
    }

    /** @deprecated Use $this->legalEntity->removeAdditionalTaxIdentifier() */
    public function removeAdditionalTaxIdentifier(int $legal_entity_id, string $tax_identifier): array|false|\Illuminate\Http\Client\Response
    {
        return $this->legalEntity->removeAdditionalTaxIdentifier($legal_entity_id, $tax_identifier);
    }

    /** @deprecated Use $this->legalEntity->delete() */
    public function deleteIdentifier(int $legal_entity_id): array|\Illuminate\Http\Client\Response
    {
        return $this->legalEntity->delete($legal_entity_id);
    }


    /**
     * getCorpPassObject
     *
     * Specific object for CorpPass flow to create
     * SG:EUN for registration.
     * @param  int $legal_entity_id
     * @param string $identifier
     * @return array|\Illuminate\Http\Client\Response
     */
    public function startCorpPassFlow(int $legal_entity_id, string $identifier): array|\Illuminate\Http\Client\Response
    {

        $payload = [
            "scheme" => "SG:UEN",
            "identifier" => $identifier,
            "superscheme" => "iso6523-actorid-upis",
            "corppass" => [
                'flow_type' => 'corppass_flow_redirect',
                'client_redirect_fail_url' => config('ninja.react_url') . '/einvoice/registration/failed',
                'client_redirect_success_url' => config('ninja.react_url') . '/einvoice/registration/success',
                'simulate_corppass' => config('ninja.app_env') === 'local',
            ],
        ];

        $r = $this->httpClient("legal_entities/{$legal_entity_id}/peppol_identifiers", (HttpVerb::POST)->value, $payload);


        /**
         *  ['corppass' => [
                    'enabled' => true,
                    'flow_type' => 'corppass_flow_redirect',
                    'signer_name' => NULL,
                    'signer_email' => NULL,
                    'corppass_url' => 'redirect the user to this URL in the front end',
                    'status' => 'corppass_initiated',
                    'client_redirect_success_url' => 'http://localhost:3000/einvoice/registration/success',
                    'client_redirect_fail_url' => 'http://localhost:3000/einvoice/registration/failed',
                ]
            ]
         */
        if ($r->successful()) {
            nlog($r->json());
            return $r->json();
        }

        return $r;
    }

    /**
     * getDocument
     *
     * @param  string $guid
     * @param  string $format json|original
     * @return array|\Illuminate\Http\Client\Response
     */
    public function getDocument(string $guid, string $format = 'json'): array|\Illuminate\Http\Client\Response
    {

        $uri = "/received_documents/{$guid}/{$format}";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, []);

        if ($r->successful()) {
            return $r->json();
        }

        return $r;

    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * getHeaders
     *
     * Base request headers
     *
     * @param  array $headers
     * @return array
     */
    private function getHeaders(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
        ], $headers);

    }

    /**
     * Http Client
     *
     * @param  string $uri
     * @param  string $verb
     * @param  array $data
     * @param  array $headers
     * @return \Illuminate\Http\Client\Response
     */
    public function httpClient(string $uri, string $verb, array $data, ?array $headers = [])
    {

        try {
            $r = Http::withToken(config('ninja.storecove_api_key'))
                ->withHeaders($this->getHeaders($headers))
            ->{$verb}("{$this->base_url}{$uri}", $data)->throw();
        } catch (ClientException $e) {
            // 4xx errors
            nlog("LEI:: {$this->legal_entity_id}");
            nlog("Client error: " . $e->getMessage());
            nlog("Response body: " . $e->getResponse()->getBody()->getContents());
        } catch (ServerException $e) {
            // 5xx errors

            nlog("LEI:: {$this->legal_entity_id}");
            nlog("Server error: " . $e->getMessage());
            nlog("Response body: " . $e->getResponse()->getBody()->getContents());
        } catch (\Illuminate\Http\Client\RequestException $e) {

            nlog("LEI:: {$this->legal_entity_id}");
            nlog("Request error: {$e->getCode()}: " . $e->getMessage());
            $responseBody = $e->response->body();
            nlog("Response body: " . $responseBody);

            return $e->response;

        }

        return $r; // @phpstan-ignore-line
    }

}
