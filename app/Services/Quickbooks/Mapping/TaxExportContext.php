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

namespace App\Services\Quickbooks\Mapping;

use App\DataMapper\QuickbooksSync;

/**
 * Immutable snapshot of the QuickBooks tax settings used while mapping an invoice.
 */
final readonly class TaxExportContext
{
    /**
     * @param  array<int, mixed>  $tax_rate_map
     * @param  array<string, mixed>  $composite_tax_code_map
     */
    public function __construct(
        public string $country,
        public bool $automatic_taxes,
        public string $taxable_code,
        public string $exempt_code,
        public array $tax_rate_map,
        public array $composite_tax_code_map,
    ) {
    }

    public static function fromQuickbooksSync(QuickbooksSync $settings): self
    {
        $country = $settings->country ?? 'US';
        $taxable_code = $settings->default_taxable_code ?? 'TAX';
        $exempt_code = $settings->default_exempt_code ?? 'NON';

        if ($country === 'US') {
            $taxable_code = 'TAX';
            $exempt_code = 'NON';
        }

        return new self(
            country: $country,
            automatic_taxes: (bool) $settings->automatic_taxes,
            taxable_code: $taxable_code,
            exempt_code: $exempt_code,
            tax_rate_map: $settings->tax_rate_map ?? [],
            composite_tax_code_map: $settings->composite_tax_code_map ?? [],
        );
    }

    public function isUs(): bool
    {
        return $this->country === 'US';
    }

    public function includesTxnTaxDetail(): bool
    {
        return !$this->automatic_taxes && $this->isUs();
    }

    public function usesTaxExcludedCalculation(): bool
    {
        return $this->automatic_taxes || !$this->isUs();
    }

    /**
     * @param  array<int, mixed>  $tax_rate_map
     * @param  array<string, mixed>  $composite_tax_code_map
     */
    public function withMaps(array $tax_rate_map, array $composite_tax_code_map): self
    {
        return new self(
            country: $this->country,
            automatic_taxes: $this->automatic_taxes,
            taxable_code: $this->taxable_code,
            exempt_code: $this->exempt_code,
            tax_rate_map: $tax_rate_map,
            composite_tax_code_map: $composite_tax_code_map,
        );
    }

    /**
     * Non-US companies cannot send the literal NON exempt code; fall back to the taxable code.
     */
    public function withNormalizedExemptCode(): self
    {
        if ($this->isUs() || $this->exempt_code !== 'NON') {
            return $this;
        }

        return new self(
            country: $this->country,
            automatic_taxes: $this->automatic_taxes,
            taxable_code: $this->taxable_code,
            exempt_code: $this->taxable_code,
            tax_rate_map: $this->tax_rate_map,
            composite_tax_code_map: $this->composite_tax_code_map,
        );
    }
}
