<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class PaymentWasDeleted extends Event {

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
