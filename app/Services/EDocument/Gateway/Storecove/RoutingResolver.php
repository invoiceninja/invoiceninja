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

use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use App\Services\EDocument\Support\GlnIdentifier;
use App\Services\EDocument\Standards\Peppol\CountryFactory;
use App\Services\EDocument\Standards\Peppol\CountryHandler;
use App\Services\EDocument\Standards\Peppol\IT as ItalyCountryHandler;

/**
 * Resolves Storecove routing metadata for a recipient.
 *
 * Single cascading pipeline:
 *  1. GLN in routing_id (bare or 0088:) → return
 *  2. Italy B2B/B2G bare CUUO + Partita IVA (IT:IVA) → IT:CUUO + IT:IVA eIdentifiers
 *  3. Italy domestic consumer (sender IT) bare CUUO + CF → IT:CUUO + IT:CF
 *  4. Explicit routing_id in "scheme:id" format → discover → return
 *  5. Foreign sender → IT consumer: IT:CF + optional non-PEC email
 *  6. Handler getCandidates() → for each: discover → return first hit
 *  7. Email fallback (individuals or Email-routed countries)
 *  8. None
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
        private ?StorecoveIdentifierValidator $identifierValidator = null,
    ) {
        $this->countryCode = $this->invoice->client->country->iso_3166_2;
        $this->classification = $this->invoice->client->classification ?? 'business';
        $this->handler = CountryFactory::make($this->countryCode);
        $this->identifierValidator ??= new StorecoveIdentifierValidator();
    }

    /**
     * Resolve routing for the recipient.
     *
     * @return array{type: string, meta: array, networks: array}
     */
    public function resolve(): array
    {
        $result = ['type' => 'none', 'meta' => [], 'networks' => []];

        // 1. GLN always wins if the client has supplied one (valid 13-digit GS1
        //    GLN bare or "0088:<GLN>"). No discovery gate — a valid GLN is the
        //    recipient's authoritative identifier. If Storecove can't find it,
        //    fail loud rather than silently rerouting to VAT/SIRET.
        if ($gln = $this->resolveGlnRoutingId()) {
            return $gln;
        }

        // 2. Italy B2B/B2G: IT:CUUO + IT:IVA (Partita IVA / VAT ID).
        if ($italyBg = $this->resolveItalyBusinessGovernmentDualIdentifiers()) {
            return $italyBg;
        }

        // 3. Italy domestic consumer: IT:CUUO + IT:CF (sender must be IT — checked inside).
        if ($italyConsumer = $this->resolveItalyDomesticIndividualDualIdentifiers()) {
            return $italyConsumer;
        }

        // 4. Explicit scheme:id routing_id override (non-GLN schemes)
        if ($explicit = $this->resolveExplicitRoutingId()) {
            return $explicit;
        }

        // 5. Foreign sender → IT consumer: IT:CF + optional email (not PEC).
        if ($italyForeign = $this->resolveItalyForeignConsumerCombinedRouting()) {
            return $italyForeign;
        }

        // 6. Handler-provided candidates — try discovery, first hit wins.
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
            $id = StorecoveIdentifierValidator::dashSignificantScheme($candidate['scheme'])
                ? preg_replace('/\s+/', '', $candidate['id'])
                : preg_replace("/[^a-zA-Z0-9]/", "", $candidate['id']);

            if (strlen($id) < 1) {
                continue;
            }

            if ($firstValid === null) {
                $firstValid = ['scheme' => $candidate['scheme'], 'id' => $id];
            }

            if ($this->proxyDiscovery($id, $candidate['scheme'])) {
                return $this->eIdentifierResult($candidate['scheme'], $id);
            }
        }

        // No discovery succeeded — use the first valid candidate from config
        if ($firstValid !== null) {
            return $this->eIdentifierResult($firstValid['scheme'], $firstValid['id']);
        }

        // 7. Email fallback for individuals
        if ($this->classification === 'individual') {
            return $this->emailResult($this->invoice->client->present()->email());
        }

        // 8. Check config for Email routing (IN, SA, IT B2C)
        $code = $this->router->resolveRouting($this->countryCode, $this->classification);
        if ($code === 'Email') {
            return $this->emailResult($this->invoice->client->present()->email());
        }

        return $result;
    }

    /**
     * Valid GS1 GLN (13 digits, ICD 0088) in routing_id wins over handler candidates.
     *
     * @see \App\Services\EDocument\Support\GlnIdentifier
     */
    private function resolveGlnRoutingId(): ?array
    {
        $routingId = trim($this->invoice->client->routing_id ?? '');

        $gln = GlnIdentifier::tryParse($routingId);
        if ($gln === null) {
            return null;
        }

        // Attempt discovery for network-override resolution, but don't gate
        // the result on it.
        $this->proxyDiscovery($gln, '0088');

        return $this->eIdentifierResult('0088', $gln);
    }

    /**
     * Italy business/government: Storecove SDI routing expects Codice Destinatario (CUUO) with Partita IVA.
     *
     * When routing_id is bare (not scheme:pairs) and both CUUO and VAT are format-valid, return both
     * eIdentifiers. Skips when a GLN is present (handled above) or when routing_id uses explicit scheme:id.
     */
    private function resolveItalyBusinessGovernmentDualIdentifiers(): ?array
    {
        if ($this->countryCode !== 'IT' || !in_array($this->classification, ['business', 'government'], true)) {
            return null;
        }

        $client = $this->invoice->client;
        $routingRaw = trim($client->routing_id ?? '');

        if ($routingRaw === '' || GlnIdentifier::isValid($routingRaw) || str_contains($routingRaw, ':')) {
            return null;
        }

        $vatClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        $cuuoClean = preg_replace("/[^a-zA-Z0-9]/", "", $routingRaw);

        if (strlen($vatClean) < 2 || strlen($cuuoClean) < 2) {
            return null;
        }

        if (!$this->identifierValidator->validFormat('IT:IVA', $vatClean)
            || !$this->identifierValidator->validFormat('IT:CUUO', $cuuoClean)) {
            return null;
        }

        return $this->eIdentifiersBundle([
            ['scheme' => 'IT:CUUO', 'id' => $cuuoClean],
            ['scheme' => 'IT:IVA', 'id' => $vatClean],
        ]);
    }

    /**
     * Italy sender → Italy consumer (Codice Fiscale + Codice Destinatario).
     */
    private function resolveItalyDomesticIndividualDualIdentifiers(): ?array
    {
        if ($this->countryCode !== 'IT' || $this->classification !== 'individual') {
            return null;
        }

        if ($this->invoice->company->country()->iso_3166_2 !== 'IT') {
            return null;
        }

        $client = $this->invoice->client;
        $routingRaw = trim($client->routing_id ?? '');

        if ($routingRaw === '' || GlnIdentifier::isValid($routingRaw) || str_contains($routingRaw, ':')) {
            return null;
        }

        $cfClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        $cuuoClean = preg_replace("/[^a-zA-Z0-9]/", "", $routingRaw);

        if (strlen($cfClean) < 2 || strlen($cuuoClean) < 2) {
            return null;
        }

        if (!$this->identifierValidator->validFormat('IT:CF', $cfClean)
            || !$this->identifierValidator->validFormat('IT:CUUO', $cuuoClean)) {
            return null;
        }

        return $this->eIdentifiersBundle([
            ['scheme' => 'IT:CUUO', 'id' => $cuuoClean],
            ['scheme' => 'IT:CF', 'id' => $cfClean],
        ]);
    }

    /**
     * Non-IT sender → IT consumer: IT:CF for SDI with optional ordinary email (PEC excluded at validation).
     */
    private function resolveItalyForeignConsumerCombinedRouting(): ?array
    {
        if ($this->countryCode !== 'IT' || $this->classification !== 'individual') {
            return null;
        }

        if ($this->invoice->company->country()->iso_3166_2 === 'IT') {
            return null;
        }

        $client = $this->invoice->client;
        $cfClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');

        if (strlen($cfClean) < 2 || !$this->identifierValidator->validFormat('IT:CF', $cfClean)) {
            return null;
        }

        $emailRaw = $client->present()->email();
        $routing = [
            'eIdentifiers' => [
                ['scheme' => 'IT:CF', 'id' => $cfClean],
            ],
        ];

        if ($emailRaw !== 'No Email Set'
            && $emailRaw !== ''
            && !ItalyCountryHandler::isItalianPecEmail($emailRaw)) {
            $routing['emails'] = [$emailRaw];
        }

        return [
            'type' => 'eIdentifiers',
            'meta' => [
                'routing' => $routing,
            ],
            'networks' => $this->resolveNetworkOverrides(),
        ];
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
            return $this->eIdentifierResult($scheme, $id);
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
        $client = $this->invoice->client;
        $client->setRelation('company', $this->invoice->company);

        $networks = $this->handler->getNetworkOverrides($client);

        $senderCode = $this->invoice->company->country()->iso_3166_2;
        if ($senderCode !== $this->countryCode) {
            $senderHandler = CountryFactory::make($senderCode);
            $networks = array_merge($networks, $senderHandler->getNetworkOverrides($client));
        }

        return array_values(array_unique($networks, SORT_REGULAR));
    }

    private function proxyDiscovery(string $identifier, string $scheme): bool
    {
        return $this->proxy
            ->setCompany($this->invoice->company)
            ->discovery($identifier, $scheme);
    }

    /**
     * @param  array<int, array{scheme: string, id: string}>  $pairs
     * @return array{type: string, meta: array, networks: array}
     */
    private function eIdentifiersBundle(array $pairs): array
    {
        return [
            'type' => 'eIdentifiers',
            'meta' => [
                'routing' => [
                    'eIdentifiers' => array_values(array_map(
                        static fn (array $p): array => ['scheme' => $p['scheme'], 'id' => $p['id']],
                        $pairs,
                    )),
                ],
            ],
            'networks' => $this->resolveNetworkOverrides(),
        ];
    }

    private function eIdentifierResult(string $scheme, string $id): array
    {
        return $this->eIdentifiersBundle([['scheme' => $scheme, 'id' => $id]]);
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
            'networks' => $this->resolveNetworkOverrides(),
        ];
    }
}
