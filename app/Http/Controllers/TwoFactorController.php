<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use PragmaRX\Google2FA\Google2FA;
use Crypt;

class TwoFactorController extends BaseController
{
    public function setupTwoFactor()
    {
        $user = auth()->user();

        if ($user->google_2fa_secret) 
            return response()->json(['message' => '2FA already enabled'], 400);
        elseif(! $user->phone)
            return response()->json(['message' => ctrans('texts.set_phone_for_two_factor')], 400);
        elseif(! $user->isVerified())
            return response()->json(['message' => 'Please confirm your account first'], 400);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
         
        $qr_code = $google2fa->getQRCodeGoogleUrl(
            config('ninja.app_name'),
            $user->email,
            $secret
        );

        $data = [
            'secret' => $secret,
            'qrCode' => $qrCode,
        ];

        return response()->json(['data' => $data], 200);
        
    }

    public function enableTwoFactor()
    {
        $user = auth()->user();
        $secret = request()->input('secret');
        $oneTimePassword = request()->input('one_time_password');

        if (! $secret || ! \Google2FA::verifyKey($secret, $oneTimePassword)) {
            return response()->json('message' > ctrans('texts.invalid_one_time_password'));
        } elseif (! $user->google_2fa_secret && $user->phone && $user->confirmed) {
            $user->google_2fa_secret = encrypt($secret);
            $user->save();
        }

        return response()->json(['message' => ctrans('texts.enabled_two_factor')], 200);
    }
}
