<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Transformers\UserTransformer;
use Crypt;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends BaseController
{
    protected $entity_type = User::class;

    protected $entity_transformer = UserTransformer::class;

    public function setupTwoFactor()
    {
        $user = auth()->user();

        if ($user->google_2fa_secret) {
            return response()->json(['message' => '2FA already enabled'], 400);
        } elseif (! $user->phone) {
            return response()->json(['message' => ctrans('texts.set_phone_for_two_factor')], 400);
        } elseif (! $user->isVerified()) {
            return response()->json(['message' => 'Please confirm your account first'], 400);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $qr_code = $google2fa->getQRCodeUrl(
            config('ninja.app_name'),
            $user->email,
            $secret
        );

        $data = [
            'secret' => $secret,
            'qrCode' => $qr_code,
        ];

        return response()->json(['data' => $data], 200);
    }

    public function enableTwoFactor()
    {
        $google2fa = new Google2FA();

        $user = auth()->user();
        $secret = request()->input('secret');
        $oneTimePassword = request()->input('one_time_password');

        if ($google2fa->verifyKey($secret, $oneTimePassword) && $user->phone && $user->email_verified_at) {
            $user->google_2fa_secret = encrypt($secret);

            $user->save();

            return response()->json(['message' => ctrans('texts.enabled_two_factor')], 200);
        } elseif (! $secret || ! $google2fa->verifyKey($secret, $oneTimePassword)) {
            return response()->json(['message' => ctrans('texts.invalid_one_time_password')], 400);
        }

        return response()->json(['message' => 'No phone record or user is not confirmed'], 400);
    }

    public function disableTwoFactor()
    {
        $user = auth()->user();
        $user->google_2fa_secret = null;
        $user->save();

        return $this->itemResponse($user);
    }
}
