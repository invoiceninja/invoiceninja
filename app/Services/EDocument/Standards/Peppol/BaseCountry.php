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

namespace App\Services\EDocument\Standards\Peppol;

use App\Models\Client;
use App\Models\Company;
use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;

class BaseCountry implements CountryHandler
{

    /**
     * Default sender mutations — no-op.
     */
    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {
        return $p_invoice;
    }

    /**
     * Default receiver mutations — no-op.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {
        return $p_invoice;
    }

    /**
     * Return the routing rules for this country.
     * Return null to fall back to the default routing rules.
     */
    public function getRoutingRules(): ?array
    {
        return null;
    }

    /**
     * Default getCandidates: resolve from StorecoveRouter config.
     *
     * Picks the routing scheme for this country/classification, then selects
     * the appropriate identifier (vat_number for VAT schemes, id_number otherwise).
     * Returns a single candidate or empty array.
     */
    public function getCandidates(object $client, string $classification, object $router): array
    {
        /** @var StorecoveRouter $router */
        $country = $client->country->iso_3166_2;
        $scheme = $router->resolveRouting($country, $classification);

        if ($scheme === 'Email' || empty($scheme)) {
            return [];
        }

        // Composite schemes like "0195:SGUENT08GA0028A" — use as-is
        if (preg_match('/^(\d{4}):(.+)$/', $scheme, $m)) {
            return [['scheme' => $m[1], 'id' => $m[2]]];
        }

        // Pick identifier: VAT schemes use vat_number, others use id_number
        $isVatScheme = str_contains($scheme, ':VAT') || str_contains($scheme, ':IVA') || str_contains($scheme, ':CF');
        $id = $isVatScheme
            ? preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '')
            : preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?: $client->vat_number ?? '');

        return strlen($id) >= 2 ? [['scheme' => $scheme, 'id' => $id]] : [];
    }

    public function getNetworkOverrides(): array
    {
        return [];
    }

    public function getAdditionalIdentifiers(array $data): array
    {
        return [];
    }

    public function getRegistrationFlow(object $storecove, int $legal_entity_id, array $data): array|\Illuminate\Http\Client\Response|null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * Individuals use email routing for most countries — no identifier columns required by default.
     */
    public function resolveRequiredClientFields(string $country, ?string $classification, StorecoveRouter $router, ?string $senderCountryCode = null): array
    {
        $classification ??= 'business';

        if ($classification === 'individual') {
            return [];
        }

        return $router->requiredClientFieldsFromEffectiveMatrix($country, $classification);
    }

    public function consumesBareRoutingId(?string $classification): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * OR semantics: any one candidate with valid format passes.
     */
    public function validateReceiverRoutingIdentifiers(Client $client, string $classification, StorecoveRouter $router, ?string $senderCountryCode = null): array
    {
        $candidates = $this->getCandidates($client, $classification, $router);

        foreach ($candidates as $candidate) {
            if ($router->validateIdentifierFormat($candidate['scheme'], $candidate['id'])) {
                return [];
            }
        }

        return [$this->buildRoutingIdentifierValidationError($candidates, $client, $router)];
    }

    public function storecoveCustomerPartyPublicIdentifiers(object $client, object $invoice, StorecoveRouter $router): array
    {
        $country = $client->country->iso_3166_2;
        $classification = $client->classification ?? 'business';

        $primary = $this->resolvePrimaryStorecoveReceiverPair($client, $router, $country, $classification);
        if ($primary === null) {
            return [];
        }

        $pairs = [$primary];

        $taxScheme = $router->resolveTaxScheme($country, $classification);
        if (!empty($taxScheme) && $taxScheme !== $primary['scheme']) {
            $vatRaw = trim($client->vat_number ?? '');
            if (strlen($vatRaw) > 1 && $router->matchesSchemeFormat($taxScheme, $vatRaw)) {
                $pairs[] = ['scheme' => $taxScheme, 'id' => $vatRaw];
            }
        }

        return $pairs;
    }
    
    /**
     * resolveCompanyScheme
     * 
     * The base case is that we always return the companys VAT and a generic ICD code 
     * 
     * @param Company $company
     * @return array
     */
    public function resolveEndpointScheme(Company $company): array
    {
        /** Prioritize GLN if Present */
        if(stripos($company->settings->id_number ?? '', '0088:') !== false){

            return [
                'scheme' => '0088',
                'id' => str_replace('0088:', '', $company->settings->id_number),    
            ];

        }

        // Fallback to VAT => ID Number.
        $endpoint_id = strlen($company->settings->vat_number) > 1 ? $company->settings->vat_number : $company->settings->id_number ?? '';
        $endpoint_id = preg_replace("/[^a-zA-Z0-9]/", "", $endpoint_id);

        /** empty string for SchemeID - should allow validation exceptions to be raised if no valid endpoint is present */
        $scheme = strlen($endpoint_id) > 1 ? '0203' : '';

        return [
            'scheme' => $scheme,
            'id' => $endpoint_id
        ];
    }

    public function resolvePartyIdentificationScheme(Company $company): ?array
    {
        return null;
    }

    /**
     * Primary receiver `publicIdentifiers` entry for the Storecove document (legal/routing id column semantics match legacy adapter).
     *
     * @return array{scheme: string, id: string}|null
     */
    protected function resolvePrimaryStorecoveReceiverPair(object $client, StorecoveRouter $router, string $country, string $classification): ?array
    {
        $scheme = $router->resolveRouting($country, $classification);

        if (empty($scheme)) {
            return null;
        }

        if ($scheme === 'Email') {
            $scheme = $router->resolveTaxScheme($country, $classification);
            if (empty($scheme)) {
                return null;
            }
        }

        $compositeEndpointId = null;
        if (preg_match('/^(\d{4}):(.+)$/', $scheme, $m)) {
            $compositeEndpointId = $m[2];
            $scheme = $router->resolveIdentifierScheme($country, $classification);
            if (empty($scheme)) {
                return null;
            }
        }

        if ($country === 'AT' && $classification === 'government') {
            return ['scheme' => 'AT:GOV', 'id' => 'b'];
        }

        if ($scheme === 'GLN' || str_contains($scheme, ':CUUO')) {
            $raw = $client->routing_id ?? '';
            if (strlen($raw) > 1) {
                return ['scheme' => $scheme, 'id' => trim($raw)];
            }

            return null;
        }

        $isVatScheme = str_contains($scheme, ':VAT') || str_contains($scheme, ':IVA') || str_contains($scheme, ':CF');
        $sources = $isVatScheme
            ? [$client->vat_number ?? '', $client->id_number ?? '']
            : [$client->id_number ?? '', $client->vat_number ?? ''];

        foreach ($sources as $raw) {
            if (strlen($raw) < 2) {
                continue;
            }

            $light = preg_replace("/[\s.]/", "", $raw);
            $heavy = preg_replace("/[^a-zA-Z0-9]/", "", $raw);
            $stripped = (stripos($heavy, $country) === 0 && strlen($heavy) > strlen($country))
                ? substr($heavy, strlen($country))
                : null;

            $variants = [$light, $heavy, $stripped];
            $seen = [];
            foreach ($variants as $val) {
                if ($val === null || $val === '' || isset($seen[$val])) {
                    continue;
                }
                $seen[$val] = true;

                if (!$router->matchesSchemeFormat($scheme, $val)) {
                    continue;
                }

                $idOut = $val;
                if ($stripped !== null && $stripped !== ''
                    && in_array($scheme, ['BE:EN', 'DK:DIGST', 'CH:UIDB'], true)
                    && $router->matchesSchemeFormat($scheme, $stripped)) {
                    $idOut = $stripped;
                }

                return ['scheme' => $scheme, 'id' => $idOut];
            }
        }

        if ($compositeEndpointId !== null) {
            return ['scheme' => $scheme, 'id' => $compositeEndpointId];
        }

        return null;
    }

    /**
     * @param  array<int, array{scheme: string, id: string}>  $candidates
     * @return array{field: string, label: string}
     */
    protected function buildRoutingIdentifierValidationError(array $candidates, Client $client, StorecoveRouter $router): array
    {
        $countryName = $client->country->full_name ?? $client->country->iso_3166_2;

        if ($candidates === []) {
            return [
                'field' => 'vat_number',
                'label' => "A valid routing identifier is required for Peppol delivery to {$countryName}.",
            ];
        }

        $parts = [];

        foreach ($candidates as $c) {
            $example = $router->getFormatExample($c['scheme']);
            $parts[] = $example
                ? "{$c['scheme']} (e.g. {$example})"
                : $c['scheme'];
        }

        return [
            'field' => 'vat_number',
            'label' => "No valid Peppol routing identifier for {$countryName}. Any one of: " . implode(', ', $parts) . '.',
        ];
    }
}
