<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class InvoiceSent extends Event {

	use SerializesModels;

    public $invoice;
    
	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

}
