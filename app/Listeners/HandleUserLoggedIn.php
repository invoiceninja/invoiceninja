<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Events\UserSignedUp;
use App\Libraries\HistoryUtils;
use App\Models\Gateway;
use App\Ninja\Repositories\AccountRepository;
use Utils;
use Auth;
use Carbon;
use Session;

/**
 * Class HandleUserLoggedIn.
 */
class HandleUserLoggedIn
{
    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * Create the event handler.
     *
     * @param AccountRepository $accountRepo
     */
    public function __construct(AccountRepository $accountRepo)
    {
        $this->accountRepo = $accountRepo;
    }

    /**
     * Handle the event.
     *
     * @param UserLoggedIn $event
     *
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
        HistoryUtils::loadHistory($users ?: Auth::user()->id);

        $account->loadLocalizationSettings();
        session([SESSION_DB_SERVER => config('database.default')]);

        if (strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iPad')) {
            Session::flash('warning', trans('texts.iphone_app_message', ['link' => link_to(NINJA_IOS_APP_URL, trans('texts.iphone_app'))]));
        } elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Android')) {
            Session::flash('warning', trans('texts.iphone_app_message', ['link' => link_to(NINJA_ANDROID_APP_URL, trans('texts.android_app'))]));
        }

        // if they're using Stripe make sure they're using Stripe.js
        $accountGateway = $account->getGatewayConfig(GATEWAY_STRIPE);
        if ($accountGateway && ! $accountGateway->getPublishableStripeKey()) {
            Session::flash('warning', trans('texts.missing_publishable_key'));
        } elseif ($account->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $account->getLogoSize() . 'KB']));
        }

        // check custom gateway id is correct
        if (! Utils::isNinja()) {
            $gateway = Gateway::find(GATEWAY_CUSTOM);
            if (! $gateway || $gateway->name !== 'Custom') {
                Session::flash('error', trans('texts.error_incorrect_gateway_ids'));
            }
        }
    }
}
