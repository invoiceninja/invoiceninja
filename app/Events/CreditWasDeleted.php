<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class CreditWasDeleted extends Event {

	use SerializesModels;

    public $credit;
    
	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
    public function __construct($credit)
    {
        $this->credit = $credit;
    }

}
