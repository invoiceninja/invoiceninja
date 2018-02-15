<?php

namespace App\Listeners;

use \Aacotroneo\Saml2\Events\Saml2LoginEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Ninja\Repositories;
use App\Models\User;
use App\Models\Account;
use Session;

class LoginListener {

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct() {
        //
    }

    /**
     * Handle the event.
     *
     * @param  Saml2LoginEvent  $event
     * @return void
     */
    public function handle(Saml2LoginEvent $event) {
        $user = $event->getSaml2User();
        $userData = [
            'id' => $user->getUserId(),
            'attributes' => $user->getAttributes(),
            'assertion' => $user->getRawSamlAssertion(),
            'sessionIndex' => $user->getSessionIndex(),
            'nameId' => $user->getNameId()
        ];

        $emailAttribute = config('saml2_settings.emailAttribute');
        $firstnameAttribute = config('saml2_settings.firstnameAttribute');
        $lastnameAttribute = config('saml2_settings.lastnameAttribute');
        $givennameAttribute = config('saml2_settings.givennameAttribute');
        $accountAttribute = config('saml2_settings.accountAttribute');

        //check if email already exists and fetch user
        $user = \App\Models\User::where('email', $userData['attributes'][$emailAttribute][0])->first();

        if ($user === null) {
            if ($givennameAttribute != '') {
                $namedata = explode(' ', $userData['attributes'][$givennameAttribute][0]);
                if (count($namedata) > 1) {
                    $firstname = $namedata[0];
                    $lastname = $namedata[1];
                } else {
                    $firstname = '';
                    $lastname = $namedata[0];
                }
            } else {
                $firstname = $userData['attributes'][$firstnameAttribute][0];
                $lastname = $userData['attributes'][$lastnameAttribute][0];
            }

            $email = $userData['attributes'][$emailAttribute][0];
            $password = bcrypt(str_random(8));

            $account = Account::where('account_key', 'LIKE', $userData['attributes'][$accountAttribute][0])->orderBy('id')->first();
            if (!$account) {
                die('Account not found');
                return;
            }
            $lastUser = User::withTrashed()->where('account_id', '=', $account->id)->orderBy('public_id', 'DESC')->first();
            $user = new User();
            $user->first_name = $firstname;
            $user->last_name = $lastname;
            $user->email = $email;
            $user->password = $password;
            $user->confirmed = true;
            $user->registered = true;
            $user->is_admin = false;
            $user->public_id = $lastUser->public_id + 1;
            $account->users()->save($user);
        } else {
            if ($user->account->account_key !== $userData['attributes'][$accountAttribute][0]) {
                die('Key does not match account');
                return;
            }
        }

        //insert sessionIndex and nameId into session
        session(['sessionIndex' => $userData['sessionIndex']]);
        session(['nameId' => $userData['nameId']]);
        //login user
        \Auth::login($user);
    }

}
