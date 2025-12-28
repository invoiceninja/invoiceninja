<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Invoice;

/**
 * Class for discount calculations.
 */
trait Discounter
{
    public function discount($amount)
    {
        if ($this->invoice->is_amount_discount == true) {
            return $this->invoice->discount;
        }

        return round($amount * ($this->invoice->discount / 100), 2);
    }
}
