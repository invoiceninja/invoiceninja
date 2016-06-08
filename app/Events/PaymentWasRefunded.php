<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class PaymentWasRefunded extends Event {

	use SerializesModels;

    public $payment;
    public $refundAmount;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($payment, $refundAmount)
    {
        $this->payment = $payment;
        $this->refundAmount = $refundAmount;
    }

}
