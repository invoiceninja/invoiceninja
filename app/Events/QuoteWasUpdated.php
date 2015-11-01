<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class QuoteWasUpdated extends Event {

	use SerializesModels;
    public $quote;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
	public function __construct($quote)
	{
		$this->quote = $quote;
	}

}
