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
use Turbo124\Beacon\Facades\LightLogs;
use App\DataMapper\Analytics\LegalEntityCreated;
use App\Enum\HttpVerb;
use App\Services\EDocument\Standards\Peppol\CountryFactory;

/**
 * Manages Storecove legal entity lifecycle:
 * creation, identifier registration, updates, and deletion.
 *
 * Extracted from Storecove.php to separate entity management
 * from document submission concerns.
 */
class LegalEntityService
{
    public function __construct(private Storecove $storecove) {}

    /**
     * Full legal entity setup: create entity, register identifiers,
     * and handle country-specific flows (CorpPass, dual identifiers).
     */
    public function setup(array $data): array|\Illuminate\Http\Client\Response
    {
        $response = $this->create($data);

        if (! is_array($response)) {
            return $response;
        }

        $legal_entity_id = $response['id'];
        $handler = CountryFactory::make($data['country']);

        // Country-specific registration flow (e.g. SG CorpPass)
        $registrationResult = $handler->getRegistrationFlow($this->storecove, $legal_entity_id, $data);
        if ($registrationResult !== null) {
            return $registrationResult;
        }

        // Standard identifier registration
        $identifier = $data['classification'] === 'individual' ? str_replace('/', '', $data['id_number']) : str_replace(" ", "", $data['vat_number']);
        $scheme = $this->storecove->router->resolveTaxScheme($data['country'], $data['classification']);

        $scheme_parts = explode(':', $identifier);
        if (count($scheme_parts) === 2) {
            $scheme = $scheme_parts[0];
            $identifier = $scheme_parts[1];
        }

        $add_identifier_response = $this->addIdentifier(
            legal_entity_id: $legal_entity_id,
            identifier: $identifier,
            scheme: $scheme,
        );

        if (! is_array($add_identifier_response)) {
            $this->delete($legal_entity_id);
            return $add_identifier_response;
        }

        // Country-specific additional identifiers (e.g. FR:SIRENE, BE:EN, DK:DIGST).
        // Best-effort (does not roll back the legal entity), but a rejection must
        // not be swallowed silently - log it so a missing identifier is detectable.
        foreach ($handler->getAdditionalIdentifiers($data) as $extra) {
            $extra_response = $this->addIdentifier(
                legal_entity_id: $legal_entity_id,
                identifier: $extra['identifier'],
                scheme: $extra['scheme'],
            );

            if (! is_array($extra_response)) {
                nlog([
                    'message' => 'Storecove rejected additional Peppol identifier registration',
                    'legal_entity_id' => $legal_entity_id,
                    'scheme' => $extra['scheme'],
                    'identifier' => $extra['identifier'],
                    'response' => $extra_response->json(),
                ]);
            }
        }

        return [
            'legal_entity_id' => $legal_entity_id,
            'tax_data' => [
                'acts_as_sender' => $data['acts_as_sender'],
                'acts_as_receiver' => $data['acts_as_receiver'],
            ],
        ];
    }

    /**
     * Create a new legal entity on Storecove.
     */
    public function create(array $data, ?Company $company = null): array|\Illuminate\Http\Client\Response
    {
        $uri = 'legal_entities';

        if ($company) {
            $data = array_merge([
                'city' => $company->settings->city,
                'country' => $company->country()->iso_3166_2,
                'county' => $company->country()->iso_3166_2 === 'IN'
                    ? (new \App\Services\EDocument\Standards\Peppol\IN())->getStateCode($company->settings->state)
                    : $company->settings->state,
                'line1' => $company->settings->address1,
                'line2' => $company->settings->address2,
                'party_name' => $company->settings->name,
                'tax_registered' => (bool) strlen($company->settings->vat_number ?? '') > 2,
                'tenant_id' => $company->company_key,
                'zip' => $company->settings->postal_code,
            ], $data);
        }

        $company_defaults = [
            'acts_as_receiver' => true,
            'acts_as_sender' => true,
            'advertisements' => ['invoice'],
        ];

        $payload = array_merge($company_defaults, $data);

        $r = $this->storecove->httpClient($uri, (HttpVerb::POST)->value, $payload);

        if ($r->successful()) {
            $data = $r->object();
            LightLogs::create(new LegalEntityCreated($data->id, $data->tenant_id))->batch();
            return $r->json();
        }

        return $r;
    }

    /**
     * Get a legal entity by ID.
     */
    public function get(int $id): array|\Illuminate\Http\Client\Response
    {
        $uri = "legal_entities/{$id}";

        $r = $this->storecove->httpClient($uri, (HttpVerb::GET)->value, []);

        if ($r->successful()) {
            return $r->json();
        }

        return $r;
    }

    /**
     * Update a legal entity.
     */
    public function update(int $id, array $data): array|\Illuminate\Http\Client\Response
    {
        $uri = "legal_entities/{$id}";

        $r = $this->storecove->httpClient($uri, (HttpVerb::PATCH)->value, $data);

        if ($r->successful()) {
            return $r->json();
        }

        return $r;
    }

    /**
     * Delete a legal entity from the network.
     */
    public function delete(int $legal_entity_id): array|\Illuminate\Http\Client\Response
    {
        $uri = "/legal_entities/{$legal_entity_id}";

        $r = $this->storecove->httpClient($uri, (HttpVerb::DELETE)->value, []);

        if ($r->successful()) {
            return [];
        }

        return $r;
    }

    /**
     * Add a Peppol identifier to a legal entity.
     */
    public function addIdentifier(int $legal_entity_id, string $identifier, string $scheme): array|\Illuminate\Http\Client\Response
    {
        $uri = "legal_entities/{$legal_entity_id}/peppol_identifiers";
        $identifier = preg_replace("/[^a-zA-Z0-9]/", "", $identifier);
        $data = [
            "identifier" => $identifier,
            "scheme" => $scheme,
            "superscheme" => "iso6523-actorid-upis",
        ];

        $r = $this->storecove->httpClient($uri, (HttpVerb::POST)->value, $data);

        if ($r->successful()) {
            return $r->json();
        }

        return $r;
    }

    /**
     * Remove a Peppol identifier from a legal entity.
     */
    public function removeIdentifier(int $legal_entity_id, string $identifier, string $scheme, string $superscheme = "iso6523-actorid-upis"): array|\Illuminate\Http\Client\Response
    {
        $uri = "/legal_entities/{$legal_entity_id}/peppol_identifiers/{$superscheme}/{$scheme}/{$identifier}";

        $r = $this->storecove->httpClient($uri, (HttpVerb::DELETE)->value, []);

        if ($r->successful()) {
            return $r->json();
        }

        return $r;
    }

    /**
     * Add an additional tax identifier for cross-border VAT registration.
     * @param int $legal_entity_id
     * @param array $data [identifier => string, scheme => string, country => string]
     */
    public function addAdditionalTaxIdentifier(int $legal_entity_id, array $data): array|\Illuminate\Http\Client\Response
    {
        $uri = "legal_entities/{$legal_entity_id}/additional_tax_identifiers";

        $data = array_merge($data, [
            "superscheme" => "iso6523-actorid-upis",
        ]);
        
        $r = $this->storecove->httpClient($uri, (HttpVerb::POST)->value, $data);

        if ($r->successful()) {
            return $r->json();
        }

        return $r;
    }

    /**
     * Remove an additional tax identifier.
     */
    public function removeAdditionalTaxIdentifier(int $legal_entity_id, string $tax_identifier): array|false|\Illuminate\Http\Client\Response
    {
        $legal_entity = $this->get($legal_entity_id);

        if (isset($legal_entity['additional_tax_identifiers']) && is_array($legal_entity['additional_tax_identifiers'])) {
            $identifier = collect($legal_entity['additional_tax_identifiers'])
                ->filter(fn($id) => $id['identifier'] == $tax_identifier)
                ->first();

            if (! $identifier) {
                return false;
            }

            $uri = "legal_entities/{$legal_entity_id}/additional_tax_identifiers/{$identifier['id']}";

            $r = $this->storecove->httpClient($uri, (HttpVerb::DELETE)->value, []);

            if ($r->successful()) {
                return [];
            }

            return $r;
        }

        return false;
    }
}
