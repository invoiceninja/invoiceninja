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
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveSchemeResolver;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Support\GlnIdentifier;

class BaseCountry implements CountryHandler
{
    public function __construct(
        private ?StorecoveIdentifierValidator $identifierValidator = null,
        private ?StorecoveSchemeResolver $schemeResolver = null,
    ) {
    }

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

    public function getNetworkOverrides(?Client $client = null): array
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
            if ($this->identifierValidator()->validFormat($candidate['scheme'], $candidate['id'], checkDigit: false)) {
                return [];
            }
        }

        return [$this->buildRoutingIdentifierValidationError($candidates, $client)];
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
            if (strlen($vatRaw) > 1 && $this->identifierValidator()->matchesSchemeFormat($taxScheme, $vatRaw)) {
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
        if ($gln = $this->glnEndpointFromIdentifier($company->settings->id_number ?? null)) {
            return $gln;
        }

        $endpoint_id = $this->rawCompanyEndpointValue($company);

        /** empty string for SchemeID - should allow validation exceptions to be raised if no valid endpoint is present */
        $scheme = strlen($endpoint_id) > 1 ? '0203' : '';

        return [
            'scheme' => $scheme,
            'id' => $endpoint_id,
        ];
    }

    /**
     * GLN ICD `0088:` prefix wins over any other identifier when present.
     * Returns null when the value is not a `0088:`-prefixed identifier.
     *
     * Used for both the supplier (`company->settings->id_number`) and the
     * buyer (`client->routing_id`) so both sides apply the same GLN check
     * (incl. {@see GlnIdentifier} checkdigit validation).
     *
     * @return array{scheme: string, id: string}|null
     */
    protected function glnEndpointFromIdentifier(?string $value): ?array
    {
        $value = (string) $value;

        if (!str_starts_with($value, '0088:')) {
            return null;
        }

        $gln = GlnIdentifier::tryParse($value);

        return [
            'scheme' => '0088',
            'id' => $gln ?? preg_replace("/[^0-9]/", "", substr($value, 5)),
        ];
    }

    /**
     * Cleaned alphanumeric raw endpoint value: VAT preferred, id_number fallback.
     */
    protected function rawCompanyEndpointValue(Company $company): string
    {
        $raw = strlen($company->settings->vat_number ?? '') > 1
            ? $company->settings->vat_number
            : ($company->settings->id_number ?? '');

        return preg_replace("/[^a-zA-Z0-9]/", "", $raw);
    }

    public function resolvePartyIdentificationScheme(Company $company): ?array
    {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * Default cascade for the buyer's electronic address:
     *   1. `routing_id` prefixed `0088:` → emit GLN scheme `0088`.
     *   2. `routing_id` shaped `NNNN:value` → split as ICD/EAS scheme + id.
     *   3. Otherwise delegate to {@see self::getCandidates()} and convert the
     *      first friendly scheme to its ISO 6523 code.
     *   4. Nothing resolvable → empty scheme + empty id (validator catches it).
     */
    public function resolveClientEndpointScheme(Client $client, StorecoveRouter $router): array
    {
        $routing_id = trim((string) ($client->routing_id ?? ''));

        if ($gln = $this->glnEndpointFromIdentifier($routing_id)) {
            return $gln;
        }

        if (preg_match('/^(\d{4}):(.+)$/', $routing_id, $matches)) {
            return [
                'scheme' => $matches[1],
                'id' => $matches[2],
            ];
        }

        $classification = $client->classification ?? 'business';
        $candidates = $this->getCandidates($client, $classification, $router);

        if (count($candidates) > 0 && !empty($candidates[0]['scheme']) && !empty($candidates[0]['id'])) {
            $candidate = $candidates[0];

            return [
                'scheme' => $this->schemeResolver()->iso6523((string) $candidate['scheme']),
                'id' => (string) $candidate['id'],
            ];
        }

        // Countries routed via email (IN, SA, IT B2C) have no Peppol EAS scheme —
        // emit EAS 0202 carrying the client's VAT / id_number / email so the
        // resulting EndpointID is a valid EAS code with a deliverable value.
        $country = $client->country->iso_3166_2 ?? null;
        if ($country !== null) {
            $code = $router->resolveRouting($country, $classification);
            if ($code === 'Email') {
                $vat = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
                if (strlen($vat) > 1) {
                    return ['scheme' => '0202', 'id' => $vat];
                }

                $idNumber = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
                if (strlen($idNumber) > 1) {
                    return ['scheme' => '0202', 'id' => $idNumber];
                }

                return [
                    'scheme' => '0202',
                    'id' => $client->present()->email(),
                ];
            }
        }

        return [
            'scheme' => '',
            'id' => '',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Default: do not emit `cac:PartyIdentification` for the buyer. Country
     * handlers may override (e.g. BE mirrors EndpointID into PartyIdentification).
     */
    public function resolveClientPartyIdentificationScheme(Client $client): ?array
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

                if (!$this->identifierValidator()->matchesSchemeFormat($scheme, $val)) {
                    continue;
                }

                $idOut = $val;
                if ($stripped !== null && $stripped !== ''
                    && in_array($scheme, ['BE:EN', 'DK:DIGST', 'CH:UIDB'], true)
                    && $this->identifierValidator()->matchesSchemeFormat($scheme, $stripped)) {
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
    protected function buildRoutingIdentifierValidationError(array $candidates, Client $client): array
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
            $example = $this->identifierValidator()->formatExample($c['scheme']);
            $parts[] = $example
                ? "{$c['scheme']} (e.g. {$example})"
                : $c['scheme'];
        }

        return [
            'field' => 'vat_number',
            'label' => "No valid Peppol routing identifier for {$countryName}. Any one of: " . implode(', ', $parts) . '.',
        ];
    }

    protected function identifierValidator(): StorecoveIdentifierValidator
    {
        return $this->identifierValidator ??= new StorecoveIdentifierValidator();
    }

    protected function schemeResolver(): StorecoveSchemeResolver
    {
        return $this->schemeResolver ??= new StorecoveSchemeResolver();
    }
}
