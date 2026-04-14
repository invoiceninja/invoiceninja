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

use App\Services\EDocument\Gateway\MutatorUtil;

interface CountryHandler
{
    /**
     * Apply sender-side mutations required by this country.
     *
     * Called when the sender (company) is located in this country.
     * Mutates the Peppol invoice and/or sets Storecove routing metadata.
     *
     * @param \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @param mixed $invoice The Invoice/Credit model
     * @param MutatorUtil $mutator_util
     * @param array $storecove_meta Current storecove metadata (passed by reference via callback)
     * @return array{p_invoice: mixed, storecove_meta: array} The mutated peppol invoice and storecove meta
     */
    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array;

    /**
     * Apply receiver-side mutations required by this country.
     *
     * Called when the receiver (client) is located in this country.
     *
     * @param \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @param mixed $invoice The Invoice/Credit model
     * @param MutatorUtil $mutator_util
     * @param array $storecove_meta Current storecove metadata
     * @return array{p_invoice: mixed, storecove_meta: array}
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array;

    /**
     * Return the routing rules for this country.
     * Format: single rule [business_type, legal_id, tax_id, routing_id]
     * or multi: [[business_type, legal_id, tax_id, routing_id], ...]
     * Return null if this country has no specific routing rules.
     */
    public function getRoutingRules(): ?array;

    /**
     * Override routing resolution for special cases (e.g. DE:STNR for individuals).
     * Return null to use default resolution logic.
     */
    public function resolveRoutingOverride(?string $classification, ?object $invoice = null): ?string;

    /**
     * Override tax scheme resolution for special cases.
     * Return null to use default resolution logic.
     */
    public function resolveTaxSchemeOverride(?string $classification, ?object $invoice = null): ?string;

    /**
     * Override identifier selection for routing.
     * Return the identifier value to use, or null to use default logic.
     *
     * Examples: FR returns id_number (SIRET), DE government returns routing_id.
     */
    public function resolveIdentifier(string $scheme, object $client): ?string;

    /**
     * Apply country-specific formatting to a routing identifier.
     * Default: return unchanged.
     *
     * Example: DK adds 'DK' prefix for DK:DIGST scheme.
     */
    public function formatIdentifier(string $identifier, string $scheme): string;

    /**
     * Return alternative discovery attempts for this country.
     * Each entry: ['scheme' => string, 'id' => string]
     * Return empty array for default (no fallbacks).
     *
     * Example: BE tries BE:EN first, then BE:VAT.
     */
    public function getDiscoveryFallbacks(string $identifier, string $countryPrefix): array;

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
     * and should return the API response array or Response on failure.
     *
     * Example: SG uses CorpPass OAuth + C5 IRAS email activation.
     */
    public function getRegistrationFlow(object $storecove, int $legal_entity_id, array $data): ?array;
}
