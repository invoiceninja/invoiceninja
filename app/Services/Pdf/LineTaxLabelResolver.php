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

namespace App\Services\Pdf;

use App\DataMapper\Tax\BaseRule;
use App\Models\Product;

final class LineTaxLabelResolver
{
    /**
     * Resolve a presentation-only label for the primary tax column.
     *
     * A null result means the renderer should use its normal tax-rate label.
     * An empty result intentionally hides reverse tax in unsupported regions.
     *
     * @param callable(float): string $formatRate
     */
    public static function resolve(object $item, string $sellerCountryCode, callable $formatRate): ?string
    {
        if (intval($item->tax_id ?? 0) !== Product::PRODUCT_TYPE_REVERSE_TAX) {
            return null;
        }

        $supportsReverseTax = $sellerCountryCode === 'GB'
            || $sellerCountryCode === 'SG'
            || $sellerCountryCode === 'AD'
            || in_array($sellerCountryCode, BaseRule::EU_COUNTRY_CODES, true);

        if (!$supportsReverseTax) {
            return '';
        }

        $label = ctrans('texts.reverse_tax');
        $rate = (float) ($item->reverse_tax_rate ?? 0);

        return $rate > 0
            ? $label . ' ' . $formatRate($rate) . '%'
            : $label;
    }
}
