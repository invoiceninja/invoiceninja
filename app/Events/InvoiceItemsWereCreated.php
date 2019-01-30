<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceItemsWereCreated.
 */
class InvoiceItemsWereCreated extends Event
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invoice;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }
}
