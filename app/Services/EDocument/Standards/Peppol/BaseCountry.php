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
