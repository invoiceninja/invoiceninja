<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class InvoiceWasRestored extends Event {

	use SerializesModels;

    public $invoice;
    public $fromDeleted;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($invoice, $fromDeleted)
    {
        $this->invoice = $invoice;
        $this->fromDeleted = $fromDeleted;
    }

}
