<?php

namespace App\Ninja\Domain\Invoice;

/**
 * Class InvoiceTotals
 */

class InvoiceTotals
{

    protected $invoice;

    /**
     * InvoiceTotals constructor.
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
