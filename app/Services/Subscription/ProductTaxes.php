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

namespace App\Services\Subscription;

class ProductTaxes
{
    public static function from(array|object $product): array
    {
        $taxes = [];

        for ($i = 1; $i <= 3; $i++) {
            $taxes["tax_name{$i}"] = (string) (data_get($product, "tax_name{$i}") ?? '');
            $taxes["tax_rate{$i}"] = (float) (data_get($product, "tax_rate{$i}") ?? 0);
        }

        return $taxes;
    }
}
