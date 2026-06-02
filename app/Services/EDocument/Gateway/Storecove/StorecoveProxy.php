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

use App\Utils\Ninja;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

class StorecoveProxy
{
    public Company $company;

    public function __construct(public Storecove $storecove) {}

    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    /**
     * Example refactor.
     * getLegalEntity
     *
     * @param  int $legal_entity_id
     * @return array
     */
    public function getLegalEntity(int $legal_entity_id): array
    {
        if (Ninja::isHosted()) {
            $response = $this->storecove->getLegalEntity($legal_entity_id);

            if (is_array($response)) { //successful response is the array
                return $response;
            }

            return $this->handleResponseError($response); //otherwise need to handle the http response returned
        }

        $uri = '/api/einvoice/peppol/legal_entity';
        $payload = ['legal_entity_id' => $legal_entity_id];

        return $this->remoteRequest($uri, $payload); //abstract the http calls
    }

    public function setup(array $data): array
    {
        $data = [
            ...$data,
            'classification' => $data['classification'] ?? $this->company->settings->classification,
            'vat_number' => $data['vat_number'] ?? $this->company->settings->vat_number,
            'id_number' => $data['id_number'] ?? $this->company->settings->id_number,
        ];

        if (Ninja::isHosted()) {

            //check if the user is already on the network.
            if ($already_registered = $this->storecove->checkNetworkStatus($data)) {
                return $already_registered;
            }

            $response = $this->storecove->setupLegalEntity($data);

            if (is_array($response)) {

                if ($this->company->account->companies()->whereNotNull('legal_entity_id')->count() == 1) {
                    \Modules\Admin\Jobs\Storecove\SendWelcomeEmail::dispatch($this->company);
                }

                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/setup', $data);
    }

    public function disconnect(): array
    {
        $data = [
            'company_key' => $this->company->company_key,
            'legal_entity_id' => $this->company->legal_entity_id,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->deleteIdentifier(
                legal_entity_id: $data['legal_entity_id'],
            );

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/disconnect', $data);
    }

    public function updateLegalEntity(array $data): array
    {
        $data = [
            ...$data,
            'legal_entity_id' => $this->company->legal_entity_id,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->updateLegalEntity($data['legal_entity_id'], $data);

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/update', $data);
    }

    public function addAdditionalTaxIdentifier(array $data): array
    {
        $scheme = $this->storecove->router->resolveTaxScheme($data['country'], $this->company->settings->classification);
        $data['identifier'] = $data['identifier'] ?? $data['vat_number'] ?? null;

        $data = [
            ...$data,
            'classification' => $this->company->settings->classification,
            'legal_entity_id' => $this->company->legal_entity_id,
            'scheme' => $scheme,
        ];

        if (Ninja::isHosted()) {

            $response = $this->storecove->legalEntity->addAdditionalTaxIdentifier($data['legal_entity_id'], $data);

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/add_additional_legal_identifier', $data);
    }

    public function c5Activate(string $name, string $email): array
    {
        $data = [
            'legal_entity_id' => $this->company->legal_entity_id,
            'id_number' => $this->company->settings->id_number,
            'name' => $name,
            'email' => $email,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->c5->activate(
                $data['legal_entity_id'],
                $data['id_number'],
                $name,
                $email,
            );

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->translateC5Unavailable($this->remoteRequest('/api/einvoice/peppol/sg/c5/activate', $data));
    }

    public function c5Deactivate(string $name, string $email): array
    {
        $data = [
            'legal_entity_id' => $this->company->legal_entity_id,
            'id_number' => $this->company->settings->id_number,
            'name' => $name,
            'email' => $email,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->c5->deactivate(
                $data['legal_entity_id'],
                $data['id_number'],
                $name,
                $email,
            );

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->translateC5Unavailable($this->remoteRequest('/api/einvoice/peppol/sg/c5/deactivate', $data));
    }

    public function c5Cancel(): array
    {
        $data = [
            'legal_entity_id' => $this->company->legal_entity_id,
            'id_number' => $this->company->settings->id_number,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->c5->cancel(
                $data['legal_entity_id'],
                $data['id_number'],
            );

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->translateC5Unavailable($this->remoteRequest('/api/einvoice/peppol/sg/c5/cancel', $data));
    }

    /**
     * Translate a 404 response from a self-hosted C5 proxy call into a clearer,
     * user-facing error. The hosted admin server only exposes the c5 endpoints
     * after the Apr 2026 admin release; until then self-hosted users would see
     * the generic "Resource not found" message, which is confusing.
     *
     * @todo remove once every supported hosted release carries the c5 routes.
     *
     * @param  array $response
     * @return array
     */
    private function translateC5Unavailable(array $response): array
    {
        if (($response['status'] ?? null) === 'error' && ($response['code'] ?? null) === 404) {
            $response['message'] = 'Singapore C5 is not yet available on the hosted server. Please try again after the next release or contact support.';
            $response['code'] = 503;
        }

        return $response;
    }

    public function removeAdditionalTaxIdentifier(array $data): array|false
    {
        $data['legal_entity_id'] = $this->company->legal_entity_id;

        if (Ninja::isHosted()) {
            $response = $this->storecove->removeAdditionalTaxIdentifier($data['legal_entity_id'], $data['vat_number']);

            if (is_array($response) || is_bool($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/remove_additional_legal_identifier', $data);
    }

    /**
     * Submit a Storecove document payload through the hosted or self-hosted path.
     *
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitDocument(array $payload): array
    {
        $payload = [
            ...$payload,
            'tenant_id' => $payload['tenant_id'] ?? $this->company->company_key,
            'account_key' => $payload['account_key'] ?? $this->company->account->key,
            'e_invoicing_token' => $payload['e_invoicing_token'] ?? $this->company->account->e_invoicing_token,
        ];

        if (! array_key_exists('forDocumentSubmissionGuid', $payload)) {
            $payload['legal_entity_id'] = $payload['legal_entity_id'] ?? $payload['legalEntityId'] ?? $this->company->legal_entity_id;
        }

        if (Ninja::isHosted()) {
            $response = $this->storecove->sendJsonDocument($payload);

            if (is_string($response)) {
                return ['guid' => str_replace('"', '', $response)];
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/submission', $payload);
    }

    /**
     * handleResponseError
     *
     * Generic error handler that can return an array response
     *
     * @param  mixed $response
     * @return array
     */
    public function handleResponseError($response): array
    {
        $error = [
            'status' => 'error',
            'message' => 'Unknown error occurred',
            'code' => $response->status() ?? 500,
        ];

        if ($response->json()) {
            $body = gettype($response->json()) === 'string'
                ? \json_decode($response->json(), associative: true)
                : $response->json();

            $error['message'] = $body['error'] ?? $body['message'] ?? $body;

            if (isset($body['errors']) && is_array($body['errors'])) {
                $error['errors'] = $body['errors'];
            }
        }

        if ($response->status() === 401) {
            $error['message'] = 'Authentication failed';
        }

        if ($response->status() === 403) {
            $error['message'] = 'Access forbidden';
        }

        if ($response->status() === 404) {
            $error['message'] = 'Resource not found';
        }

        nlog([
            'Storecove API Error' => [
                'status' => $response->status(),
                'body' => $response->body(),
                'error' => $error,
            ],
        ]);

        return $error;
    }

    /**
     * Check if a recipient is discoverable on the PEPPOL network.
     *
     * Hosted: calls Storecove directly.
     * Self-hosted: proxies through the hosted Ninja server.
     */
    public function discovery(string $identifier, string $scheme): bool
    {
        if (Ninja::isHosted()) {
            return $this->storecove->discovery($identifier, $scheme);
        }

        $payload = [
            'identifier' => $identifier,
            'scheme' => $scheme,
        ];

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders($this->getHeaders())
            ->post('/api/einvoice/peppol/discovery', $payload);

        if ($response->successful()) {
            return ($response->json()['discovered'] ?? false) === true;
        }

        // @todo remove this 404 branch after the next hosted release deploys the discovery route.
        // Pre-deploy the hosted server returns 404; fall back to the pre-discovery behaviour
        // (assume discoverable) so routing is not silently degraded for self-hosted users.
        if ($response->status() === 404) {
            return true;
        }

        return false;
    }

    private function remoteRequest(string $uri, array $payload = []): array
    {

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders($this->getHeaders())
            ->post($uri, $payload);

        if ($response->successful()) {
            if ($response->hasHeader('X-EINVOICE-QUOTA')) {

                /**
                 * @var \App\Models\Account $account
                 */
                $account = $this->company->account;

                $account->e_invoice_quota = (int) $response->header('X-EINVOICE-QUOTA');
                $account->save();
            }

            return $response->json();
        }

        return $this->handleResponseError($response);
    }

    private function getHeaders(): array
    {

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-EInvoice-Token' => $this->company->account->e_invoicing_token,
            "X-Requested-With" => "XMLHttpRequest",
        ];

    }
}
