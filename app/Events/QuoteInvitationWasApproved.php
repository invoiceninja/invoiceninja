<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class QuoteInvitationWasApproved extends Event {

	use SerializesModels;

    public $quote;
    public $invoice;
    public $invitation;
    
	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($quote, $invoice, $invitation)
    {
        $this->quote = $quote;
        $this->invoice = $invoice;
        $this->invitation = $invitation;
    }

}
