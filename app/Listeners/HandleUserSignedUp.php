<?php namespace App\Listeners;

use Utils;
use Auth;
use App\Events\UserSignedUp;
use App\Ninja\Repositories\AccountRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class HandleUserSignedUp {

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
	 * @param  UserSignedUp  $event
	 * @return void
	 */
	public function handle(UserSignedUp $event)
	{
        if (!Utils::isNinjaProd()) {
            $this->accountRepo->registerUser(Auth::user());
        }
	}

}
