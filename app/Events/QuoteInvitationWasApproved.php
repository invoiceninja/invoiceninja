<?php namespace App\Events;


use App\Models\Invitation;
use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

class QuoteInvitationWasApproved extends Event
{
    use SerializesModels;

    public $quote;

    /**
     * @var Invoice
     */
    public $invoice;

    /**
     * @var Invitation
     */
    public $invitation;

    /**
     * Create a new event instance.
     *
     * @param $quote
     * @param Invoice $invoice
     * @param Invitation $invitation
     */
    public function __construct($quote, Invoice $invoice, Invitation $invitation)
    {
        $this->quote = $quote;
        $this->invoice = $invoice;
        $this->invitation = $invitation;
    }
}
