<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Quote;

use App\Events\Quote\QuoteWasApproved;
use App\Factory\CloneQuoteToInvoiceFactory;
use App\Models\Invoice;
use App\Models\Quote;
use App\Repositories\QuoteRepository;
use App\Services\Quote\CreateInvitations;
use App\Services\Quote\GetQuotePdf;
use App\Utils\Ninja;

class QuoteService
{
    protected $quote;

    public $invoice;

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

        if ($this->quote->client->getSetting('auto_convert_quote') === true) {
            $this->convert();
        }

        return $this;
    }

    public function convert() :self
    {
        if ($this->quote->invoice_id) {
            return $this;
        }

        $convert_quote = new ConvertQuote($this->quote->client);

        $this->invoice = $convert_quote->run($this->quote);

        $this->quote->fresh();

        return $this;
    }

    public function getQuotePdf($contact = null)
    {
        return (new GetQuotePdf($this->quote, $contact))->run();
    }

    public function sendEmail($contact = null) :self
    {
        $send_email = new SendEmail($this->quote, null, $contact);

        $send_email->run();

        return $this;
    }

    /**
     * Applies the invoice number.
     * @return $this InvoiceService object
     */
    public function applyNumber() :self
    {
        $apply_number = new ApplyNumber($this->quote->client);

        $this->quote = $apply_number->run($this->quote);

        return $this;
    }

    public function markSent() :self
    {
        $mark_sent = new MarkSent($this->quote->client, $this->quote);

        $this->quote = $mark_sent->run();

        return $this;
    }

    public function setStatus($status) :self
    {
        $this->quote->status_id = $status;

        return $this;
    }

    public function approve($contact = null) :self
    {
        $this->setStatus(Quote::STATUS_APPROVED)->save();

        if(!$contact)
            $contact = $this->quote->invitations->first()->contact;

        event(new QuoteWasApproved($contact, $this->quote, $this->quote->company, Ninja::eventVars()));

        $invoice = null;

        if ($this->quote->client->getSetting('auto_convert_quote')) {
            $this->convert();
        }

        if ($this->quote->client->getSetting('auto_archive_quote')) {
            $quote_repo = new QuoteRepository();
            $quote_repo->archive($this->quote);
        }

        return $this;
    }

    public function convertToInvoice()
    {

        //to prevent circular references we need to explicit call this here.
        $mark_approved = new MarkApproved($this->quote->client);
        $this->quote = $mark_approved->run($this->quote);

        $this->convert();

        return $this->invoice;
    }

    public function isConvertable() :bool
    {
        if ($this->quote->invoice_id) {
            return false;
        }

        if ($this->quote->status_id == Quote::STATUS_EXPIRED) {
            return false;
        }

        return true;
    }

    public function fillDefaults()
    {
        $settings = $this->quote->client->getMergedSettings();

        if(! $this->quote->design_id) 
            $this->quote->design_id = $this->decodePrimaryKey($settings->quote_design_id);
            
        if(!isset($this->quote->footer))
            $this->quote->footer = $settings->quote_footer;

        if(!isset($this->quote->terms))
            $this->quote->terms = $settings->quote_terms;

        
        return $this;        
    }

    /**
     * Saves the quote.
     * @return Quote|null
     */
    public function save() : ?Quote
    {
        $this->quote->save();

        return $this->quote;
    }
}
