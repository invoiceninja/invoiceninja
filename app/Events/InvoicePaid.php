<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class InvoicePaid extends Event {

	use SerializesModels;

    public $payment;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
	public function __construct($payment)
	{
		$this->payment = $payment;
	}

}
