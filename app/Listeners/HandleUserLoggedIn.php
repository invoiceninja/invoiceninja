<?php namespace App\Listeners;

use Utils;
use Auth;
use Carbon;
use Session;
use App\Events\UserLoggedIn;
use App\Events\UserSignedUp;
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

        if (empty($account->last_login)) {
            event(new UserSignedUp());
        }

        $account->last_login = Carbon::now()->toDateTimeString();
        $account->save();

        $users = $this->accountRepo->loadAccounts(Auth::user()->id);
        Session::put(SESSION_USER_ACCOUNTS, $users);

        $account->loadLocalizationSettings();

        // if they're using Stripe make sure they're using Stripe.js 
        $accountGateway = $account->getGatewayConfig(GATEWAY_STRIPE);
        if ($accountGateway && ! $accountGateway->getPublishableStripeKey()) {
            Session::flash('warning', trans('texts.missing_publishable_key'));
        } elseif ($account->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $account->getLogoSize() . 'KB']));
        }
	}

}
