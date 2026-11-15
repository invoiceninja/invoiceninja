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
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Support\GlnIdentifier;

/**
 * Finland: explicit GLN first, then FI:OVT2 from ID Number or routing_id.
 */
class FI extends BaseCountry
{
    public function getCandidates(object $client, string $classification, object $router): array
    {
        if ($gln = GlnIdentifier::tryParse((string) ($client->routing_id ?? ''))) {
            return [['scheme' => '0088', 'id' => $gln]];
        }

        if (!in_array($classification, ['business', 'government'], true)) {
            return [];
        }

        foreach ([$client->id_number ?? '', $client->routing_id ?? ''] as $raw_identifier) {
            $identifier = preg_replace('/^(?:0216|FI:OVT2):/', '', trim((string) $raw_identifier));
            $identifier = preg_replace('/[\s.\-]/', '', $identifier);

            if ($this->identifierValidator()->matchesSchemeFormat('FI:OVT2', $identifier)) {
                return [['scheme' => 'FI:OVT2', 'id' => $identifier]];
            }
        }

        return [];
    }

    public function consumesBareRoutingId(?string $classification): bool
    {
        return in_array($classification, ['business', 'government'], true);
    }

    public function validateReceiverRoutingIdentifiers(Client $client, string $classification, StorecoveRouter $router, ?string $sender_country_code = null): array
    {
        if ($this->getCandidates($client, $classification, $router) !== []) {
            return [];
        }

        $field = trim((string) ($client->id_number ?? '')) === ''
            && trim((string) ($client->routing_id ?? '')) !== ''
                ? 'routing_id'
                : 'id_number';

        return [[
            'field' => $field,
            'label' => 'Provide a valid Finnish OVT (FI:OVT2) in ID Number or routing_id, e.g. 003712345678, or a GLN as 0088: followed by 13 digits.',
        ]];
    }

    public function resolveClientEndpointScheme(Client $client, StorecoveRouter $router): array
    {
        $identifier = $this->getCandidates($client, $client->classification ?? 'business', $router)[0] ?? null;

        return [
            'scheme' => $identifier === null ? '' : $this->schemeResolver()->iso6523($identifier['scheme']),
            'id' => $identifier['id'] ?? '',
        ];
    }

    protected function resolvePrimaryStorecoveReceiverPair(object $client, StorecoveRouter $router, string $country, string $classification): ?array
    {
        return $this->getCandidates($client, $classification, $router)[0] ?? null;
    }
}
