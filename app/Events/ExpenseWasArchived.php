<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class ExpenseWasArchived extends Event {

	use SerializesModels;

    public $expense;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
	public function __construct($espense)
	{
		$this->expense = $expense;
	}

}
