<?php

namespace App\Http\Controllers;

use PragmaRX\Google2FA\Google2FA;
use Crypt;

class Google2FAController extends Controller
{
    public function enableTwoFactor()
    {
        $user = auth()->user();

        if ($user->google_2fa_secret) {
            return redirect('/settings/user_details');
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->google_2fa_secret = Crypt::encrypt($secret);
        $user->save();

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
}
