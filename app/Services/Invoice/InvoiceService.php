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
use App\Models\Payment;
use App\Services\Client\ClientService;
use App\Services\Invoice\ApplyNumber;
use App\Services\Invoice\ApplyPayment;
use App\Services\Invoice\CreateInvitations;
use App\Services\Invoice\GetInvoicePdf;
use App\Services\Invoice\MarkInvoicePaid;
use App\Services\Invoice\MarkSent;
use App\Services\Invoice\UpdateBalance;
use Illuminate\Support\Carbon;

class InvoiceService
{
    private $invoice;

    protected $client_service;

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
     * Apply a payment amount to an invoice.
     * @param  Payment $payment        The Payment
     * @param  float   $payment_amount The Payment amount
     * @return InvoiceService          Parent class object
     */
    public function applyPayment(Payment $payment, float $payment_amount)
    {
        $apply_payment = new ApplyPayment($this->invoice);

        $this->invoice = $apply_payment($payment, $payment_amount);

        return $this;
    }

    /**
     * Update an invoice balance
     * @param  float $balance_adjustment The amount to adjust the invoice by
     * a negative amount will REDUCE the invoice balance, a positive amount will INCREASE
     * the invoice balance
     * @return InvoiceService                     Parent class object
     */
    public function updateBalance($balance_adjustment)
    {
        $update_balance = new UpdateBalance($this->invoice);

        $this->invoice = $update_balance($balance_adjustment);

        return $this;
    }

    public function createInvitations()
    {
        $create_invitation = new CreateInvitations();

        $this->invoice = $create_invitation($this->invoice);

        return $this;
    }

    public function markSent()
    {
        $mark_sent = new MarkSent($this->invoice->client);

        $this->invoice = $mark_sent($this->invoice);

        return $this;
    }

    public function getInvoicePdf()
    {
        $get_invoice_pdf = new GetInvoicePdf();

        return $get_invoice_pdf($this->invoice);
    }

    public function sendEmail($contact)
    {
        $send_email = new SendEmail($this->invoice);

        return $send_email->run(null, $contact);
    }

    public function markViewed()
    {
        $this->invoice->last_viewed = Carbon::now()->format('Y-m-d H:i');

        return $this;
    }

    /* One liners */
    public function setDueDate()
    {
        $this->invoice->due_date = Carbon::now()->addDays($this->invoice->client->getSetting('payment_terms'));

        return $this;
    }

    public function setStatus($status)
    {
        $this->invoice->status_id = $status;

        return $this;
    }

    public function clearPartial()
    {
        $this->invoice->partial = null;
        $this->invoice->partial_due_date = null;

        return $this;
    }

    public function updatePartial($amount)
    {
        $this->invoice->partial += $amount;

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
