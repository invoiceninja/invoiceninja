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

namespace App\Helpers\Invoice;

/**
 * Class for tax calculations.
 */
trait Taxer
{
    public function taxer($amount, $tax_rate)
    {
        return round($amount * (($tax_rate ? $tax_rate : 0) / 100), 2);
    }

    public function calcAmountLineTax($tax_rate, $amount)
    {
        return $this->formatValue(($amount * $tax_rate / 100), 2);
    }

    public function calcInclusiveLineTax($tax_rate, $amount)
    {
        return $this->formatValue($amount - ($amount / (1 + ($tax_rate / 100))), 2);
    }
}
