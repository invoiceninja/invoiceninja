<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class InvoiceInvitationWasEmailed extends Event {

	use SerializesModels;
    
    public $invitation;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($invitation)
    {
        $this->invitation = $invitation;
    }

}
