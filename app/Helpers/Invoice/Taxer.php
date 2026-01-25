<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Invoice;

/**
 * Class for tax calculations.
 */
trait Taxer
{
    public function taxer($amount, $tax_rate)
    {
        if(!$tax_rate || $tax_rate == 0) {
            return 0;
        }

        return round(\App\Utils\BcMath::mul($amount, $tax_rate/100), 2, PHP_ROUND_HALF_UP);
        // return round(($amount * (($tax_rate ? $tax_rate : 0) / 100)), 2);
    }

    public function calcAmountLineTax($tax_rate, $amount)
    {
        $tax_amount = ($amount * $tax_rate / 100);
        
        if($this->peppol_enabled) {
            return $tax_amount;
        }

        return $this->formatValue(($amount * $tax_rate / 100), 2);
    }

    public function calcInclusiveLineTax($tax_rate, $amount)
    {
        return $this->formatValue($amount - ($amount / (1 + ($tax_rate / 100))), 2);
    }
}
