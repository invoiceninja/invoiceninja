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

class BaseCountry implements CountryHandler
{
    /**
     * Default sender mutations — no-op.
     * Override in country-specific subclasses.
     */
    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {
        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Default receiver mutations — no-op.
     * Override in country-specific subclasses.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
        array $storecove_meta
    ): array {
        return ['p_invoice' => $p_invoice, 'storecove_meta' => $storecove_meta];
    }

    /**
     * Return the routing rules for this country.
     * Return null to fall back to StorecoveRouter's built-in rules.
     */
    public function getRoutingRules(): ?array
    {
        return null;
    }

    /**
     * Override routing resolution for special cases.
     * Return null to use default resolution logic.
     */
    public function resolveRoutingOverride(?string $classification, ?object $invoice = null): ?string
    {
        return null;
    }

    /**
     * Override tax scheme resolution for special cases.
     * Return null to use default resolution logic.
     */
    public function resolveTaxSchemeOverride(?string $classification, ?object $invoice = null): ?string
    {
        return null;
    }

    /**
     * Build a Storecove routing structure from an array of identifiers.
     */
    protected function buildRouting(array $identifiers): array
    {
        return [
            'routing' => [
                'eIdentifiers' => $identifiers,
            ],
        ];
    }

    /**
     * Set email routing on storecove meta.
     */
    protected function setEmailRouting(array $storecove_meta, string $email): array
    {
        if (isset($storecove_meta['routing']['emails'])) {
            $storecove_meta['routing']['emails'][] = $email;
        } else {
            $storecove_meta['routing']['emails'] = [$email];
        }

        return $storecove_meta;
    }

    /**
     * Merge new meta into existing storecove meta.
     */
    protected function mergeMeta(array $storecove_meta, array $new_meta): array
    {
        return array_merge_recursive($storecove_meta, $new_meta);
    }
}
