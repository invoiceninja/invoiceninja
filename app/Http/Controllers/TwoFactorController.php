<?php

namespace App\Http\Controllers;

use PragmaRX\Google2FA\Google2FA;
use Crypt;

class TwoFactorController extends Controller
{
    public function setupTwoFactor()
    {
        $user = auth()->user();

        if ($user->google_2fa_secret || ! $user->phone || ! $user->confirmed) {
            return redirect('/settings/user_details');
        }

        $google2fa = new Google2FA();

        if ($secret = session('2fa:secret')) {
            // do nothing
        } else {
            $secret = $google2fa->generateSecretKey();
            session(['2fa:secret' => $secret]);
        }

        $qrCode = $google2fa->getQRCodeGoogleUrl(
            APP_NAME,
            $user->email,
            $secret
        );

        $data = [
            'secret' => $secret,
            'qrCode' => $qrCode,
        ];

        return view('users.two_factor', $data);
    }

    public function enableTwoFactor()
    {
        $user = auth()->user();
        $secret = session('2fa:secret');
        $oneTimePassword = request('one_time_password');

        if (! $secret || ! \Google2FA::verifyKey($secret, $oneTimePassword)) {
            return redirect('settings/enable_two_factor')->withError(trans('texts.invalid_one_time_password'));
        } elseif (! $user->google_2fa_secret && $user->phone && $user->confirmed) {
            $user->google_2fa_secret = Crypt::encrypt($secret);
            $user->save();

            session()->forget('2fa:secret');
            session()->flash('message', trans('texts.enabled_two_factor'));
        }

        return redirect('settings/user_details');
    }
}
