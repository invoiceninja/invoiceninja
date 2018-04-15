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
        $user = auth()->user();
        $account = $user->account;

        if (! Utils::isNinja() && empty($account->last_login)) {
            event(new UserSignedUp());
        }

        $account->last_login = Carbon::now()->toDateTimeString();
        $account->save();

        if ($user->failed_logins > 0) {
            $user->failed_logins = 0;
            $user->save();
        }

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
        if ($accountGateway && ! $accountGateway->getPublishableKey()) {
            Session::flash('warning', trans('texts.missing_publishable_key'));
        } elseif ($account->isLogoTooLarge()) {
            Session::flash('warning', trans('texts.logo_too_large', ['size' => $account->getLogoSize() . 'KB']));
        }

        if (! Utils::isNinja()) {
            // check custom gateway id is correct
            $gateway = Gateway::find(GATEWAY_CUSTOM1);
            if (! $gateway || $gateway->name !== 'Custom') {
                Session::flash('error', trans('texts.error_incorrect_gateway_ids'));
            }

            // make sure APP_KEY and APP_CIPHER are in the .env file
            $appKey = env('APP_KEY');
            $appCipher = env('APP_CIPHER');
            if (! $appKey || ! $appCipher) {
                $fp = fopen(base_path().'/.env', 'a');
                if (! $appKey) {
                    fwrite($fp, "\nAPP_KEY=" . config('app.key'));
                }
                if (! $appCipher) {
                    fwrite($fp, "\nAPP_CIPHER=" . config('app.cipher'));
                }
                fclose($fp);
            }

            // warn if using the default app key
            if (in_array(config('app.key'), ['SomeRandomString', 'SomeRandomStringSomeRandomString', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'])) {
                Session::flash('error', trans('texts.error_app_key_set_to_default'));
            } elseif (in_array($appCipher, ['MCRYPT_RIJNDAEL_256', 'MCRYPT_RIJNDAEL_128'])) {
                Session::flash('error', trans('texts.mcrypt_warning', ['command' => '<code>php artisan ninja:update-key --legacy=true</code>']));
            }
        }
    }
}
