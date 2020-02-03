<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Services\Client\ClientService;
use App\Services\Invoice\ApplyNumber;
use App\Services\Invoice\MarkInvoicePaid;

class InvoiceService
{
    private $invoice;

    private $client_service;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;

        $this->client_service = new ClientService($invoice->client);

    }

    /**
     * Marks as invoice as paid 
     * and executes child sub functions
     * @return $this InvoiceService object
     */
    public function markPaid()
    {
        $mark_invoice_paid = new MarkPaid($this->client_service);
        
        $this->invoice = $mark_invoice_paid($this->invoice);

        return $this;
    }

    /**
     * Applies the invoice number
     * @return $this InvoiceService object
     */
    public function applyNumber()
    {
        $apply_number = new ApplyNumber($this->invoice->client);

        $this->invoice = $apply_number($this->invoice);

        return $this;
    }

    /**
     * Saves the invoice
     * @return Invoice object 
     */
    public function save() :?Invoice
    {
        $this->invoice->save();

        return $this->invoice;
    }
}
