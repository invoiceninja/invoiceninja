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
    public function getNetworkOverrides(?Client $client = null): array;

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
     * Whether a bare routing_id (no "scheme:id" prefix) is this country's native routing input.
     */
    public function consumesBareRoutingId(?string $classification): bool;

    public function resolveEndpointScheme(Company $company): array;

    public function resolvePartyIdentificationScheme(Company $company): ?array;

    /**
     * Resolve the buyer's `cbc:EndpointID` scheme + value.
     *
     * MUST always return an array. When neither a routing identifier nor a
     * resolvable candidate is available, return `['scheme' => '', 'id' => '']`
     * so Peppol validation surfaces the misconfiguration (BR-CL-25 /
     * PEPPOL-EN16931-CL008) rather than silently emitting an undeliverable
     * endpoint.
     *
     * Country handlers MUST return an ICD/EAS code from the CEF EAS code list.
     * The builder does not enforce this; non-conforming schemes will surface
     * as schematron errors at validation time.
     *
     * @param  Client            $client
     * @param  StorecoveRouter   $router
     * @return array{scheme: string, id: string}
     */
    public function resolveClientEndpointScheme(Client $client, StorecoveRouter $router): array;

    /**
     * Resolve the buyer's optional `cac:PartyIdentification` scheme + value.
     *
     * Return `null` to skip emitting `cac:PartyIdentification` for the
     * customer. When non-null the builder will emit the entry verbatim.
     *
     * Per BR-CL-10, schemeID values for PartyIdentification SHOULD belong to
     * ISO 6523 ICD codes (`0xxx`). Country handlers are responsible for
     * returning a compliant code.
     *
     * @param  Client $client
     * @return array{scheme: string, id: string}|null
     */
    public function resolveClientPartyIdentificationScheme(Client $client): ?array;
}
