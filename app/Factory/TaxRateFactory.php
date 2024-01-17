<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\TaxRate;

class TaxRateFactory
{
    public static function create($company_id, $user_id): TaxRate
    {
        $tax_rate = new TaxRate();

        $tax_rate->name = '';
        $tax_rate->rate = '';
        $tax_rate->company_id = $company_id;
        $tax_rate->user_id = $user_id;

        return $tax_rate;
    }
}
