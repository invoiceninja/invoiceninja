<?php namespace App\Events;

use App\Events\Event;

use Illuminate\Queue\SerializesModels;

class UserSettingsChanged extends Event {

	use SerializesModels;

    public $user;

	/**
	 * Create a new event instance.
	 *
	 * @return void
	 */
	public function __construct($user = false)
	{
        $this->user = $user;
	}

}
