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
 * Single cascading pipeline:
 *  1. Explicit routing_id in "scheme:id" format → discover → return
 *  2. Handler getCandidates() → for each: discover → return first hit
 *  3. Email fallback (individuals or Email-routed countries)
 *  4. None
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

        // 1. GLN always wins if the client has supplied one (bare 14 digits
        //    or "0088:<14digits>"). No discovery gate — a valid GLN is the
        //    recipient's authoritative identifier. If Storecove can't find it,
        //    fail loud rather than silently rerouting to VAT/SIRET.
        if ($gln = $this->resolveGlnRoutingId()) {
            return $gln;
        }

        // 2. Explicit scheme:id routing_id override (non-GLN schemes)
        if ($explicit = $this->resolveExplicitRoutingId()) {
            return $explicit;
        }

        // 3. Handler-provided candidates — try discovery, first hit wins.
        //    If no discovery succeeds, use the first valid candidate (config-based).
        $candidates = $this->handler->getCandidates(
            $this->invoice->client,
            $this->classification,
            $this->router,
        );

        $firstValid = null;

        foreach ($candidates as $candidate) {
            // Preserve dashes for schemes where they are semantically part of
            // the identifier (e.g. DE:LWID). For everything else, strip them
            // so minor user formatting doesn't block discovery.
            $id = StorecoveRouter::dashSignificantScheme($candidate['scheme'])
                ? preg_replace('/\s+/', '', $candidate['id'])
                : preg_replace("/[^a-zA-Z0-9]/", "", $candidate['id']);

            if (strlen($id) < 1) {
                continue;
            }

            if ($firstValid === null) {
                $firstValid = ['scheme' => $candidate['scheme'], 'id' => $id];
            }

            if ($this->proxyDiscovery($id, $candidate['scheme'])) {
                $result = $this->eIdentifierResult($candidate['scheme'], $id);
                $result['networks'] = $this->resolveNetworkOverrides();
                return $result;
            }
        }

        // No discovery succeeded — use the first valid candidate from config
        if ($firstValid !== null) {
            $result = $this->eIdentifierResult($firstValid['scheme'], $firstValid['id']);
            $result['networks'] = $this->resolveNetworkOverrides();
            return $result;
        }

        // 4. Email fallback for individuals
        if ($this->classification === 'individual') {
            return $this->emailResult($this->invoice->client->present()->email());
        }

        // 5. Check config for Email routing (IN, SA, IT B2C)
        $code = $this->router->setInvoice($this->invoice)
            ->resolveRouting($this->countryCode, $this->classification);
        if ($code === 'Email') {
            return $this->emailResult($this->invoice->client->present()->email());
        }

        return $result;
    }

    /**
     * If the client has a valid GLN in routing_id (bare 14 digits or
     * "0088:<14digits>"), route via scheme 0088 unconditionally — GLN wins
     * over every handler candidate. Discovery is attempted for network
     * overrides, but a failed discovery does NOT cause fall-through: the
     * value stands.
     */
    private function resolveGlnRoutingId(): ?array
    {
        $routingId = trim($this->invoice->client->routing_id ?? '');

        if ($routingId === '' || !StorecoveRouter::isValidGln($routingId)) {
            return null;
        }

        $gln = str_starts_with($routingId, '0088:')
            ? substr($routingId, 5)
            : $routingId;

        // Attempt discovery for network-override resolution, but don't gate
        // the result on it.
        $this->proxyDiscovery($gln, '0088');

        $result = $this->eIdentifierResult('0088', $gln);
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
     * Resolve network overrides from both receiver and sender country handlers.
     *
     * Receiver networks (e.g. SE Svefaktura) are required when sending TO that country.
     * Sender networks (e.g. PL KSeF, RO ANAF) are required when sending FROM that country.
     */
    private function resolveNetworkOverrides(): array
    {
        $networks = $this->handler->getNetworkOverrides();

        $senderCode = $this->invoice->company->country()->iso_3166_2;
        if ($senderCode !== $this->countryCode) {
            $senderHandler = CountryFactory::make($senderCode);
            $networks = array_merge($networks, $senderHandler->getNetworkOverrides());
        }

        return $networks;
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
