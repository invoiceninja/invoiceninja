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

use App\Services\EDocument\Standards\Peppol\CountryFactory;
use App\Services\EDocument\Standards\Peppol\CountryHandler;

/**
 * Resolves Storecove routing metadata for a recipient.
 *
 * Determines how Storecove should deliver a document by resolving the correct
 * scheme + identifier pair (eIdentifiers) or email fallback. Resolution order:
 *
 *  1. No identifiers + individual → email routing
 *  2. Explicit routing_id in "scheme:id" format → use directly (after discovery)
 *  3. Resolve scheme via StorecoveRouter, pick identifier, apply country formatting,
 *     attempt discovery, build eIdentifiers
 *
 * Returns a result array with 'type' (eIdentifiers|email|none) and the routing data.
 */
class RoutingResolver
{
    private string $countryCode;
    private string $classification;
    private CountryHandler $handler;

    public function __construct(
        private $invoice,
        private StorecoveProxy $proxy,
        private StorecoveRouter $router,
    ) {
        $this->countryCode = $this->invoice->client->country->iso_3166_2;
        $this->classification = $this->invoice->client->classification ?? 'business';
        $this->handler = CountryFactory::make($this->countryCode);
    }

    /**
     * Resolve routing for the recipient.
     *
     * @return array{type: string, meta: array, networks: array}
     */
    public function resolve(): array
    {
        $result = ['type' => 'none', 'meta' => [], 'networks' => []];

        // 1. No identifiers — email or bail
        if (strlen($this->invoice->client->vat_number ?? '') < 2 && strlen($this->invoice->client->id_number ?? '') < 2) {
            if ($this->classification === 'individual') {
                return $this->emailResult($this->invoice->client->present()->email());
            }
            return $result;
        }

        // 2. Explicit routing_id override (scheme:id format)
        if ($explicit = $this->resolveExplicitRoutingId()) {
            return $explicit;
        }

        // 3. Resolve via routing rules
        $code = $this->router->setInvoice($this->invoice)
            ->resolveRouting($this->countryCode, $this->classification);

        if ($code === 'Email') {
            return $this->emailResult($this->invoice->client->present()->email());
        }

        // Pick the correct identifier for the scheme
        $identifier = $this->resolveIdentifier($code);

        // Clean special characters
        $identifier = preg_replace("/[^a-zA-Z0-9]/", "", $identifier);

        // Country-specific formatting
        $identifier = $this->formatIdentifier($identifier, $code);

        // Country-specific discovery (BE dual-scheme cascade)
        if ($countryResult = $this->resolveCountryDiscovery($identifier, $code)) {
            return $countryResult;
        }

        // Composite routing codes (e.g. "0195:SGUENT08GA0028A")
        if (preg_match('/^(\d{4}):(.+)$/', $code, $m)) {
            $result = $this->eIdentifierResult($m[1], $m[2]);
        } else {
            $result = $this->eIdentifierResult($code, $identifier);
        }

        // Network overrides (Svefaktura for SE)
        $result['networks'] = $this->resolveNetworkOverrides();

        return $result;
    }

    /**
     * Try explicit routing_id in "scheme:id" format.
     */
    private function resolveExplicitRoutingId(): ?array
    {
        $routingId = $this->invoice->client->routing_id ?? '';

        if (stripos($routingId, ':') === false) {
            return null;
        }

        $parts = explode(':', $routingId);

        if (count($parts) !== 2) {
            return null;
        }

        [$scheme, $id] = $parts;

        if ($this->proxyDiscovery($id, $scheme)) {
            $result = $this->eIdentifierResult($scheme, $id);
            $result['networks'] = $this->resolveNetworkOverrides();
            return $result;
        }

        return null;
    }

    /**
     * Pick the correct identifier value based on scheme type and country.
     */
    private function resolveIdentifier(string $code): string
    {
        $client = $this->invoice->client;

        // Country handler can override identifier selection
        $override = $this->handler->resolveIdentifier($code, $client);
        if ($override !== null) {
            return $override;
        }

        // IT:CUUO uses routing_id (Codice Destinatario)
        if (str_contains($code, ':CUUO') && strlen($client->routing_id ?? '') > 1) {
            return $client->routing_id;
        }

        $is_vat_scheme = str_contains($code, ':VAT') || str_contains($code, ':IVA') || str_contains($code, ':CF');

        // Non-VAT schemes prefer id_number if it matches the format
        if (!$is_vat_scheme && strlen($client->id_number ?? '') > 1) {
            $clean_id = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number);
            if ($this->router->matchesSchemeFormat($code, $clean_id)) {
                return $client->id_number;
            }
            return $client->vat_number ?? '';
        }

        $identifier = $client->vat_number ?? '';

        if (!$identifier) {
            $identifier = $this->getFallbackIdentifier();
        }

        return $identifier;
    }

    /**
     * Fallback: extract identifier from client when primary selection is empty.
     */
    private function getFallbackIdentifier(): string
    {
        $client = $this->invoice->client;

        if ($this->classification === 'individual' && strlen($client->id_number ?? '') > 2) {
            return preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        }

        return preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
    }

    /**
     * Apply country-specific identifier formatting via handler.
     */
    private function formatIdentifier(string $identifier, string $code): string
    {
        return $this->handler->formatIdentifier($identifier, $code);
    }

    /**
     * Country-specific discovery with fallback schemes via handler.
     */
    private function resolveCountryDiscovery(string $identifier, string $code): ?array
    {
        $fallbacks = $this->handler->getDiscoveryFallbacks($identifier, $this->countryCode);

        if (empty($fallbacks)) {
            return null;
        }

        foreach ($fallbacks as $fallback) {
            if ($this->proxyDiscovery($fallback['id'], $fallback['scheme'])) {
                return $this->eIdentifierResult($fallback['scheme'], $fallback['id']);
            }
        }

        return null;
    }

    /**
     * Resolve network overrides via country handler.
     */
    private function resolveNetworkOverrides(): array
    {
        return $this->handler->getNetworkOverrides();
    }

    private function proxyDiscovery(string $identifier, string $scheme): bool
    {
        return $this->proxy
            ->setCompany($this->invoice->company)
            ->discovery($identifier, $scheme);
    }

    private function eIdentifierResult(string $scheme, string $id): array
    {
        return [
            'type' => 'eIdentifiers',
            'meta' => [
                'routing' => [
                    'eIdentifiers' => [
                        ['scheme' => $scheme, 'id' => $id],
                    ],
                ],
            ],
            'networks' => [],
        ];
    }

    private function emailResult(string $email): array
    {
        return [
            'type' => 'email',
            'meta' => [
                'routing' => [
                    'emails' => [$email],
                ],
            ],
            'networks' => [],
        ];
    }
}
