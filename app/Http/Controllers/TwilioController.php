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

use App\Http\Requests\Twilio\ConfirmSmsRequest;
use App\Http\Requests\Twilio\GenerateSmsRequest;
use App\Libraries\MultiDB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;
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

        if(MultiDB::hasPhoneNumber($request->phone))
            return response()->json(['message' => 'This phone number has already been verified with another account'], 400);

        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        $twilio = new Client($sid, $token);


        try {
            $verification = $twilio->verify
                                   ->v2
                                   ->services(config('ninja.twilio_verify_sid'))
                                   ->verifications
                                   ->create($request->phone, "sms");
        }
        catch(\Exception $e) {

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
        

        if($verification_check->status == 'approved'){

            $account->account_sms_verified = true;
            $account->save();

            return response()->json(['message' => 'SMS verified'], 200);
        }


        return response()->json(['message' => 'SMS not verified'], 400);


    }

}
