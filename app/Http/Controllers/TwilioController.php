<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Twilio\Confirm2faRequest;
use App\Http\Requests\Twilio\ConfirmSmsRequest;
use App\Http\Requests\Twilio\Generate2faRequest;
use App\Http\Requests\Twilio\GenerateSmsRequest;
use App\Libraries\MultiDB;
use App\Models\User;
use Twilio\Rest\Client;

class TwilioController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function generate(GenerateSmsRequest $request)
    {
        $account = auth()->user()->company()->account;

        if (MultiDB::hasPhoneNumber($request->phone)) {
            return response()->json(['message' => 'This phone number has already been verified with another account'], 400);
        }

        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        $twilio = new Client($sid, $token);


        try {
            $verification = $twilio->verify
                                   ->v2
                                   ->services(config('ninja.twilio_verify_sid'))
                                   ->verifications
                                   ->create($request->phone, "sms");
        } catch(\Exception $e) {
            return response()->json(['message' => 'Invalid phone number please use + country code + number ie. +15552343334'], 400);
        }

        $account->account_sms_verification_code = $verification->sid;
        $account->account_sms_verification_number = $request->phone;
        $account->save();

        return response()->json(['message' => 'Code sent.'], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function confirm(ConfirmSmsRequest $request)
    {
        $account = auth()->user()->company()->account;

        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        $twilio = new Client($sid, $token);

        $verification_check = $twilio->verify
                                     ->v2
                                     ->services(config('ninja.twilio_verify_sid'))
                                     ->verificationChecks
                                     ->create([
                                             "to" => $account->account_sms_verification_number,
                                             "code" => $request->code
                                       ]);
        

        if ($verification_check->status == 'approved') {
            $account->account_sms_verified = true;
            $account->save();

            //on confirmation we set the users phone number.
            $user = auth()->user();
            $user->phone = $account->account_sms_verification_number;
            $user->verified_phone_number = true;
            $user->save();

            return response()->json(['message' => 'SMS verified'], 200);
        }


        return response()->json(['message' => 'SMS not verified'], 400);
    }

    public function generate2faResetCode(Generate2faRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Unable to retrieve user.'], 400);
        }

        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        $twilio = new Client($sid, $token);

        try {
            $verification = $twilio->verify
                                   ->v2
                                   ->services(config('ninja.twilio_verify_sid'))
                                   ->verifications
                                   ->create($user->phone, "sms");
        } catch(\Exception $e) {
            return response()->json(['message' => 'Invalid phone number on file, we are unable to reset. Please contact support.'], 400);
        }

        $user->sms_verification_code = $verification->sid;
        $user->save();

        return response()->json(['message' => 'Code sent.'], 200);
    }

    public function confirm2faResetCode(Confirm2faRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Unable to retrieve user.'], 400);
        }

        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        $twilio = new Client($sid, $token);

        $verification_check = $twilio->verify
                                     ->v2
                                     ->services(config('ninja.twilio_verify_sid'))
                                     ->verificationChecks
                                     ->create([
                                             "to" => $user->phone,
                                             "code" => $request->code
                                       ]);
        
        if ($verification_check->status == 'approved') {
            if ($request->query('validate_only') == 'true') {
                $user->verified_phone_number = true;
                $user->save();
                return response()->json(['message' => 'SMS verified'], 200);
            }

            $user->google_2fa_secret = '';
            $user->sms_verification_code = '';
            $user->save();

            return response()->json(['message' => 'SMS verified, 2FA disabled.'], 200);
        }

        return response()->json(['message' => 'SMS not verified.'], 400);
    }

    public function validatePhoneNumber()
    {
        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        $twilio = new Client($sid, $token);

        $phone_number = $twilio->lookups->v1->phoneNumbers("0417918829")
                                            ->fetch(["countryCode" => "AU"]);

        print($phone_number);
    }
}
