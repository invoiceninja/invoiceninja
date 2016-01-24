<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class PaymentWasRestored extends Event {

	use SerializesModels;

    public $payment;
    public $fromDeleted;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($payment, $fromDeleted)
    {
        $this->payment = $payment;
        $this->fromDeleted = $fromDeleted;
    }

}
