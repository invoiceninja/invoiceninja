<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class InvoiceInvitationWasViewed extends Event {

	use SerializesModels;

    public $invoice;
    public $invitation;
    
	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($invoice, $invitation)
    {
        $this->invoice = $invoice;
        $this->invitation = $invitation;
    }

}
