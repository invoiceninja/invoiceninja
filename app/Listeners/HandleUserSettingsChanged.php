<?php namespace App\Listeners;

use Auth;

use App\Events\UserSettingsChanged;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleUserSettingsChanged {

	/**
	 * Create the event handler.
	 *
	 * @return void
	 */
	public function __construct()
	{
		//
	}

	/**
	 * Handle the event.
	 *
	 * @param  UserSettingsChanged  $event
	 * @return void
	 */
	public function handle(UserSettingsChanged $event)
	{
        $account = Auth::user()->account;
        $account->loadLocalizationSettings();
	}

}
