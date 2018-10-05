<?php

namespace App\Models\Traits;

/**
 * Class CalculatesInvoiceTotals.
 */
class CalculatesInvoiceTotals
{
    protected $invoice;

    /**
     * InvoiceTotals constructor.
     *
     * @param $invoice
     */
    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function calculate()
    {
    }

    private function sumLineItems()
    {
    }
}
