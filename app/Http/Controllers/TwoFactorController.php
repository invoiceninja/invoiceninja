<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\TwoFactor\EnableTwoFactorRequest;
use App\Models\User;
use App\Transformers\UserTransformer;
use App\Utils\Ninja;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends BaseController
{
    protected $entity_type = User::class;

    protected $entity_transformer = UserTransformer::class;

    public function setupTwoFactor()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (strlen($user->google_2fa_secret ?? '') > 2) {
            return response()->json(['message' => '2FA already enabled'], 400);
        } elseif (Ninja::isSelfHost()) {

        } elseif (! $user->phone) {
            return response()->json(['message' => ctrans('texts.set_phone_for_two_factor')], 400);
        } elseif (! $user->isVerified()) {
            return response()->json(['message' => 'Please confirm your account first'], 400);
        }

        $google2fa = new Google2FA();
        $google2fa->setAlgorithm(\PragmaRX\Google2FA\Support\Constants::SHA512);
        $secret = $google2fa->generateSecretKey(32);

        $qr_code = $google2fa->getQRCodeUrl(
            config('ninja.app_name'),
            $user->email,
            $secret
        );
 
        // Append algorithm parameter for SHA512
        $qr_code .= '&algorithm=SHA512';

        $data = [
            'secret' => $secret,
            'qrCode' => $qr_code,
        ];

        return response()->json(['data' => $data], 200);
    }

    public function enableTwoFactor(EnableTwoFactorRequest $request)
    {     
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $secret = $request->input('secret');
        $oneTimePassword = $request->input('one_time_password');

     // Try SHA512 first (new algorithm)
        $google2fa = new Google2FA();
        $google2fa->setAlgorithm(\PragmaRX\Google2FA\Support\Constants::SHA512);
        $timestamp = $google2fa->verifyKeyNewer($secret, $oneTimePassword, 0);

        // Fall back to SHA1 for manual entry (authenticator apps default to SHA1)
        if ($timestamp === false) {
            $google2fa = new Google2FA();
            $timestamp = $google2fa->verifyKeyNewer($secret, $oneTimePassword, 0);
        }

        if ($timestamp !== false && $user->phone && $user->email_verified_at) {
            $user->google_2fa_secret = encrypt($secret);
            $user->google_2fa_ts = $timestamp;
            $user->save();

            return response()->json(['message' => ctrans('texts.enabled_two_factor')], 200);
        } elseif (! $secret || $timestamp === false) {
            return response()->json(['message' => ctrans('texts.invalid_one_time_password')], 400);
        } elseif (! $user->phone) {
            return response()->json(['message' => ctrans('texts.set_phone_for_two_factor')], 400);
        } elseif (! $user->isVerified()) {
            return response()->json(['message' => 'Please confirm your account first'], 400);
        }

        return response()->json(['message' => 'No phone record or user is not confirmed'], 400);
    }

    /*
    * @param App\Models\User $user
    * @param App\Models\User auth()->user()
    */

    public function disableTwoFactor()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $user->google_2fa_secret = null;
           $user->google_2fa_ts = null;
        $user->save();

        return $this->itemResponse($user);
    }
}
