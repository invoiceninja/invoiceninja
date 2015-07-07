<?php namespace App\Listeners;

use Utils;
use Auth;
use Carbon;
use Session;
use App\Events\UserLoggedIn;
use App\Ninja\Repositories\AccountRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleUserLoggedIn {

    protected $accountRepo;

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
	 * @param  UserLoggedIn  $event
	 * @return void
	 */
	public function handle(UserLoggedIn $event)
	{
        $account = Auth::user()->account;

        if (!Utils::isNinja() && Auth::user()->id == 1 && empty($account->last_login)) {
            $this->accountRepo->registerUser(Auth::user());
        }

        $account->last_login = Carbon::now()->toDateTimeString();
        $account->save();

        $users = $this->accountRepo->loadAccounts(Auth::user()->id);
        Session::put(SESSION_USER_ACCOUNTS, $users);

        $account->loadLocalizationSettings();
	}

}
