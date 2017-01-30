<?php

namespace App\Http\Controllers;

use Auth;
use Input;
use Redirect;
use Session;
use URL;

class BlueVineController extends BaseController
{
    public function signup()
    {
        $user = Auth::user();

        $data = [
            'personal_user_full_name' => Input::get('name'),
            'business_phone_number' => Input::get('phone'),
            'email' => Input::get('email'),
            'personal_fico_score' => intval(Input::get('fico_score')),
            'business_annual_revenue' => intval(Input::get('annual_revenue')),
            'business_monthly_average_bank_balance' => intval(Input::get('average_bank_balance')),
            'business_inception_date' => date('Y-m-d', strtotime(Input::get('business_inception'))),
            'partner_internal_business_id' => 'ninja_account_' . $user->account_id,
        ];

        if (! empty(Input::get('quote_type_factoring'))) {
            $data['invoice_factoring_offer'] = true;
            $data['desired_credit_line'] = intval(Input::get('desired_credit_limit')['invoice_factoring']);
        }

        if (! empty(Input::get('quote_type_loc'))) {
            $data['line_of_credit_offer'] = true;
            $data['desired_credit_line_for_loc'] = intval(Input::get('desired_credit_limit')['line_of_credit']);
        }

        $api_client = new \GuzzleHttp\Client();
        try {
            $response = $api_client->request('POST',
                'https://app.bluevine.com/api/v1/user/register_external?' . http_build_query([
                    'external_register_token' => env('BLUEVINE_PARTNER_TOKEN'),
                    'c' => env('BLUEVINE_PARTNER_UNIQUE_ID'),
                    'signup_parent_url' => URL::to('/bluevine/completed'),
                ]), [
                    'json' => $data,
                ]
            );
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            if ($ex->getCode() == 403) {
                $response_body = $ex->getResponse()->getBody(true);
                $response_data = json_decode($response_body);

                return response()->json([
                    'error' => true,
                    'message' => $response_data->reason,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                ]);
            }
        }

        $company = $user->account->company;
        $company->bluevine_status = 'signed_up';
        $company->save();

        $quote_data = json_decode($response->getBody());

        return response()->json($quote_data);
    }

    public function hideMessage()
    {
        $user = Auth::user();

        if ($user) {
            $company = $user->account->company;
            $company->bluevine_status = 'ignored';
            $company->save();
        }

        return 'success';
    }

    public function handleCompleted()
    {
        Session::flash('message', trans('texts.bluevine_completed'));

        return Redirect::to('/dashboard');
    }
}
