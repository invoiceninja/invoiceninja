<?php namespace App\Listeners;

use Auth;
use Session;
use App\Events\UserSettingsChanged;
use App\Ninja\Repositories\AccountRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleUserSettingsChanged {

	/**
	 * Create the event handler.
	 *
	 * @return void
	 */
	public function __construct(AccountRepository $accountRepo)
	{
        $this->accountRepo = $accountRepo;
	}

	/**
	 * Handle the event.
	 *
	 * @param  UserSettingsChanged  $event
	 * @return void
	 */
	public function handle(UserSettingsChanged $event)
	{
        if (Auth::check()) {
            $account = Auth::user()->account;
            $account->loadLocalizationSettings();

            $users = $this->accountRepo->loadAccounts(Auth::user()->id);
            Session::put(SESSION_USER_ACCOUNTS, $users);
        }
	}

}
