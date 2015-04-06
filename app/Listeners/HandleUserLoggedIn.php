<?php namespace App\Listeners;

use Auth;
use Carbon;
use App\Events\UserLoggedIn;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleUserLoggedIn {

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
	 * @param  UserLoggedIn  $event
	 * @return void
	 */
	public function handle(UserLoggedIn $event)
	{        
        $account = Auth::user()->account;
        $account->last_login = Carbon::now()->toDateTimeString();
        $account->save();

        $account->loadLocalizationSettings();
	}

}
