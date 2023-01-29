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
 * Class for discount calculations.
 */
trait Balancer
{
    public function balance($total, $invoice)
    {
        if (isset($this->invoice->id) && $this->invoice->id >= 1) {
            return round($total - ($this->invoice->amount - $this->invoice->balance), 2);
        }

        return $total;
    }
}
