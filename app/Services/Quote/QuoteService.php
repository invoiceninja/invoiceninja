<?php
namespace App\Services\Quote;

use App\Models\Invoice;
use App\Models\Quote;
use App\Repositories\QuoteRepository;
use App\Services\Quote\CreateInvitations;

class QuoteService
{
    protected $quote;

    public function __construct($quote)
    {
        $this->quote = $quote;
    }

    public function createInvitations()
    {
        $create_invitation = new CreateInvitations();

        $this->quote = $create_invitation->run($this->quote);

        return $this;
    }

    public function markApproved()
    {
        $mark_approved = new MarkApproved($this->quote->client);

        $this->quote = $mark_approved->run($this->quote);

        if($this->quote->client->getSetting('auto_convert_quote') === true) {
            $convert_quote = new ConvertQuote($this->quote->client);
            $this->quote = $convert_quote->run($this->quote);
        }

        return $this;
    }

    public function getQuotePdf($contact)
    {
        $get_invoice_pdf = new GetQuotePdf();

        return $get_invoice_pdf($this->quote, $contact);
    }

    public function sendEmail($contact) :QuoteService
    {
        $send_email = new SendEmail($this->quote);

        $send_email->run(null, $contact);

        return $this;
    }

    /**
     * Applies the invoice number
     * @return $this InvoiceService object
     */
    public function applyNumber() :QuoteService
    {
        $apply_number = new ApplyNumber($this->quote->client);

        $this->quote = $apply_number->run($this->quote);

        return $this;
    }

    public function markSent() :QuoteService
    {
        $mark_sent = new MarkSent($this->quote->client);

        $this->quote = $mark_sent->run($this->quote);

        return $this;
    }

    public function setStatus($status) :QuoteService
    {
        $this->quote->status_id = $status;

        return $this;
    }

    public function approve() :QuoteService
    {

        $this->setStatus(Quote::STATUS_APPROVED)->save();

        $invoice = null;

        if($this->quote->client->getSetting('auto_convert_quote')){
            $invoice = $this->convertToInvoice();
            $this->linkInvoiceToQuote($invoice)->save();
        }

        if($this->quote->client->getSetting('auto_archive_quote')) {
            $quote_repo = new QuoteRepository();
            $quote_repo->archive($this->quote);
        }

        return $this;

    }

    /**
     * Where we convert a quote to an invoice we link the two entities via the invoice_id parameter on the quote table
     * @param  object $invoice The Invoice object
     * @return object          QuoteService
     */
    public function linkInvoiceToQuote($invoice) :QuoteService
    {
        $this->quote->invoice_id = $invoice->id;

        return $this;
    }

    public function convertToInvoice() :Invoice
    {
        Invoice::unguard();

            $invoice = new Invoice((array) $this->quote);
            $invoice->status_id = Invoice::STATUS_SENT;
            $invoice->due_date = null;
            $invoice->invitations = null;
            $invoice->number = null;
            $invoice->save();

        Invoice::reguard();

        $invoice->service()->markSent()->createInvitations()->save();   

        return $invoice;
    }

    /**
     * Saves the quote
     * @return Quote|null
     */
    public function save() : ?Quote
    {
        $this->quote->save();
        return $this->quote;
    }
}
