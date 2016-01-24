<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class QuoteInvitationWasViewed extends Event {

	use SerializesModels;

    public $quote;
    public $invitation;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($quote, $invitation)
    {
        $this->quote = $quote;
        $this->invitation = $invitation;
    }

}
