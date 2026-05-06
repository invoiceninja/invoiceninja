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

interface CountryHandler
{
    /**
     * Apply sender-side UBL mutations required by this country.
     *
     * Called when the sender (company) is located in this country.
     * Mutates the Peppol invoice document only — routing is handled by RoutingResolver.
     *
     * @param \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @param mixed $invoice The Invoice/Credit model
     * @param MutatorUtil $mutator_util
     * @return mixed The mutated Peppol document
     */
    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed;

    /**
     * Apply receiver-side UBL mutations required by this country.
     *
     * Called when the receiver (client) is located in this country.
     * Mutates the Peppol invoice document only — routing is handled by RoutingResolver.
     *
     * @param \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @param mixed $invoice The Invoice/Credit model
     * @param MutatorUtil $mutator_util
     * @return mixed The mutated Peppol document
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed;

    /**
     * Return the routing rules for this country.
     * Format: single rule [business_type, legal_id, tax_id, routing_id]
     * or multi: [[business_type, legal_id, tax_id, routing_id], ...]
     * Return null if this country has no specific routing rules.
     */
    public function getRoutingRules(): ?array;

    /**
     * Return ordered routing candidates for a recipient.
     *
     * Each candidate: ['scheme' => string, 'id' => string]
     * RoutingResolver tries each in order — first discoverable hit wins.
     * Return empty array to fall through to email/none fallback.
     *
     * @param object $client The client model
     * @param string $classification business|government|individual
     * @param \App\Services\EDocument\Gateway\Storecove\StorecoveRouter $router
     * @return array<int, array{scheme: string, id: string}>
     */
    public function getCandidates(object $client, string $classification, object $router): array;

    /**
     * Return additional network configurations for this country's receivers.
     * Return empty array for default (no network overrides).
     *
     * Example: SE enables Svefaktura network.
     */
    public function getNetworkOverrides(): array;

    /**
     * Return additional Peppol identifiers to register during legal entity setup.
     * Each entry: ['identifier' => string, 'scheme' => string]
     * Return empty array for countries that only need the primary identifier.
     *
     * Example: BE registers both BE:VAT and BE:EN for HERMES network support.
     */
    public function getAdditionalIdentifiers(array $data): array;

    /**
     * Return a custom registration flow if this country requires one.
     * Return null to use the standard identifier registration.
     *
     * The callback receives (Storecove $storecove, int $legal_entity_id, array $data)
     * and should return the API response array, Response on failure, or null
     * to use the standard identifier registration.
     *
     * Example: SG uses CorpPass OAuth + C5 IRAS email activation.
     */
    public function getRegistrationFlow(object $storecove, int $legal_entity_id, array $data): array|\Illuminate\Http\Client\Response|null;

    /**
     * Required client fields for forms / UX, derived from routing policy for this country.
     *
     * @param  ?string  $senderCountryCode  ISO 3166-2 for the sender (company) when known; null if generic map / unknown.
     * @return array<string, string> Keys are client attribute names, values are scheme labels
     */
    public function resolveRequiredClientFields(string $country, ?string $classification, StorecoveRouter $router, ?string $senderCountryCode = null): array;

    /**
     * Whether a bare routing_id (no "scheme:id" prefix) is this country's native routing input.
     */
    public function consumesBareRoutingId(?string $classification): bool;

    /**
     * Validates receiver Peppol routing identifiers offline (format regex only).
     *
     * Default: success if any candidate from getCandidates() passes validateIdentifierFormat (OR semantics).
     *
     * @param  ?string  $senderCountryCode  ISO 3166-2 for the sender (company); pass null only when unavailable.
     * @return array<int, array{field: string, label: string}>
     */
    public function validateReceiverRoutingIdentifiers(Client $client, string $classification, StorecoveRouter $router, ?string $senderCountryCode = null): array;
}
