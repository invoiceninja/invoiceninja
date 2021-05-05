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

namespace App\PaymentDrivers\WePay;

use App\PaymentDrivers\WePayPaymentDriver;
use Illuminate\Http\Request;

class Setup
{
    public $wepay;

    public function __construct(WePayPaymentDriver $wepay)
    {
        $this->wepay = $wepay;
    }

    public function boot($data)
    {
        /*
        'user_id',
        'company_key',
         */
        
        return render('gateways.wepay.signup.index', $data);
    }
 

    public function processSignup(Request $request)
    {

    }   
}


/*
protected function setupWePay($accountGateway, &$response)
    {
        $user = Auth::user();
        $account = $user->account;

        $rules = [
            'company_name' => 'required',
            'tos_agree' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'country' => 'required|in:US,CA,GB',
        ];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('gateways/create')
                ->withErrors($validator)
                ->withInput();
        }

        if (! $user->email) {
            $user->email = trim(Input::get('email'));
            $user->first_name = trim(Input::get('first_name'));
            $user->last_name = trim(Input::get('last_name'));
            $user->save();
        }

        try {
            $wepay = Utils::setupWePay();

            $userDetails = [
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'email' => Input::get('email'),
                'first_name' => Input::get('first_name'),
                'last_name' => Input::get('last_name'),
                'original_ip' => \Request::getClientIp(true),
                'original_device' => \Request::server('HTTP_USER_AGENT'),
                'tos_acceptance_time' => time(),
                'redirect_uri' => URL::to('gateways'),
                'scope' => 'manage_accounts,collect_payments,view_user,preapprove_payments,send_money',
            ];

            $wepayUser = $wepay->request('user/register/', $userDetails);

            $accessToken = $wepayUser->access_token;
            $accessTokenExpires = $wepayUser->expires_in ? (time() + $wepayUser->expires_in) : null;

            $wepay = new WePay($accessToken);

            $accountDetails = [
                'name' => Input::get('company_name'),
                'description' => trans('texts.wepay_account_description'),
                'theme_object' => json_decode(WEPAY_THEME),
                'callback_uri' => $accountGateway->getWebhookUrl(),
                'rbits' => $account->present()->rBits,
                'country' => Input::get('country'),
            ];

            if (Input::get('country') == 'CA') {
                $accountDetails['currencies'] = ['CAD'];
                $accountDetails['country_options'] = ['debit_opt_in' => boolval(Input::get('debit_cards'))];
            } elseif (Input::get('country') == 'GB') {
                $accountDetails['currencies'] = ['GBP'];
            }

            $wepayAccount = $wepay->request('account/create/', $accountDetails);

            try {
                $wepay->request('user/send_confirmation/', []);
                $confirmationRequired = true;
            } catch (\WePayException $ex) {
                if ($ex->getMessage() == 'This access_token is already approved.') {
                    $confirmationRequired = false;
                } else {
                    throw $ex;
                }
            }

            $accountGateway->gateway_id = GATEWAY_WEPAY;
            $accountGateway->setConfig([
                'userId' => $wepayUser->user_id,
                'accessToken' => $accessToken,
                'tokenType' => $wepayUser->token_type,
                'tokenExpires' => $accessTokenExpires,
                'accountId' => $wepayAccount->account_id,
                'state' => $wepayAccount->state,
                'testMode' => WEPAY_ENVIRONMENT == WEPAY_STAGE,
                'country' => Input::get('country'),
            ]);

            if ($confirmationRequired) {
                Session::flash('message', trans('texts.created_wepay_confirmation_required'));
            } else {
                $updateUri = $wepay->request('/account/get_update_uri', [
                    'account_id' => $wepayAccount->account_id,
                    'redirect_uri' => URL::to('gateways'),
                ]);

                $response = Redirect::to($updateUri->uri);

                return true;
            }

            $response = Redirect::to("gateways/{$accountGateway->public_id}/edit");

            return true;
        } catch (\WePayException $e) {
            Session::flash('error', $e->getMessage());
            $response = Redirect::to('gateways/create')
                ->withInput();

            return false;
        }
    }
 */


/*


rbits


    private function createRBit($type, $source, $properties)
    {
        $data = new stdClass();
        $data->receive_time = time();
        $data->type = $type;
        $data->source = $source;
        $data->properties = new stdClass();

        foreach ($properties as $key => $val) {
            $data->properties->$key = $val;
        }

        return $data;
    }

    public function rBits()
    {
        $account = $this->entity;
        $user = $account->users()->first();
        $data = [];

        $data[] = $this->createRBit('business_name', 'user', ['business_name' => $account->name]);
        $data[] = $this->createRBit('industry_code', 'user', ['industry_detail' => $account->present()->industry]);
        $data[] = $this->createRBit('comment', 'partner_database', ['comment_text' => 'Logo image not present']);
        $data[] = $this->createRBit('business_description', 'user', ['business_description' => $account->present()->size]);

        $data[] = $this->createRBit('person', 'user', ['name' => $user->getFullName()]);
        $data[] = $this->createRBit('email', 'user', ['email' => $user->email]);
        $data[] = $this->createRBit('phone', 'user', ['phone' => $user->phone]);
        $data[] = $this->createRBit('website_uri', 'user', ['uri' => $account->website]);
        $data[] = $this->createRBit('external_account', 'partner_database', ['is_partner_account' => 'yes', 'account_type' => 'Invoice Ninja', 'create_time' => time()]);

        return $data;
    }
 */