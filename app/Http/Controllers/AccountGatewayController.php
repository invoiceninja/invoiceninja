<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountGatewaySettings;
use App\Models\AccountGateway;
use App\Models\Gateway;
use App\Services\AccountGatewayService;
use Auth;
use Input;
use Redirect;
use Session;
use stdClass;
use URL;
use Utils;
use Validator;
use View;
use WePay;

class AccountGatewayController extends BaseController
{
    protected $accountGatewayService;

    public function __construct(AccountGatewayService $accountGatewayService)
    {
        //parent::__construct();

        $this->accountGatewayService = $accountGatewayService;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_PAYMENTS);
    }

    public function getDatatable()
    {
        return $this->accountGatewayService->getDatatable(Auth::user()->account_id);
    }

    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("gateways/$publicId/edit");
    }

    public function edit($publicId)
    {
        $accountGateway = AccountGateway::scope($publicId)->firstOrFail();
        $config = $accountGateway->getConfig();

        if ($accountGateway->gateway_id != GATEWAY_CUSTOM) {
            foreach ($config as $field => $value) {
                $config->$field = str_repeat('*', strlen($value));
            }
        }

        $data = self::getViewModel($accountGateway);
        $data['url'] = 'gateways/'.$publicId;
        $data['method'] = 'PUT';
        $data['title'] = trans('texts.edit_gateway') . ' - ' . $accountGateway->gateway->name;
        $data['config'] = $config;
        $data['hiddenFields'] = Gateway::$hiddenFields;
        $data['selectGateways'] = Gateway::where('id', '=', $accountGateway->gateway_id)->get();

        return View::make('accounts.account_gateway', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    public function store()
    {
        return $this->save();
    }

    /**
     * Displays the form for account creation.
     */
    public function create()
    {
        if (! \Request::secure() && ! Utils::isNinjaDev()) {
            Session::flash('warning', trans('texts.enable_https'));
        }

        $account = Auth::user()->account;
        $accountGatewaysIds = $account->gatewayIds();
        $otherProviders = Input::get('other_providers');

        if (! Utils::isNinja() || ! env('WEPAY_CLIENT_ID') || Gateway::hasStandardGateway($accountGatewaysIds)) {
            $otherProviders = true;
        }

        $data = self::getViewModel();
        $data['url'] = 'gateways';
        $data['method'] = 'POST';
        $data['title'] = trans('texts.add_gateway');

        if ($otherProviders) {
            $availableGatewaysIds = $account->availableGatewaysIds();
            $data['primaryGateways'] = Gateway::primary($availableGatewaysIds)->orderBy('sort_order')->get();
            $data['secondaryGateways'] = Gateway::secondary($availableGatewaysIds)->orderBy('name')->get();
            $data['hiddenFields'] = Gateway::$hiddenFields;

            return View::make('accounts.account_gateway', $data);
        } else {
            return View::make('accounts.account_gateway_wepay', $data);
        }
    }

    private function getViewModel($accountGateway = false)
    {
        $selectedCards = $accountGateway ? $accountGateway->accepted_credit_cards : 0;
        $user = Auth::user();
        $account = $user->account;

        $creditCardsArray = unserialize(CREDIT_CARDS);
        $creditCards = [];
        foreach ($creditCardsArray as $card => $name) {
            if ($selectedCards > 0 && ($selectedCards & $card) == $card) {
                $creditCards[$name['text']] = ['value' => $card, 'data-imageUrl' => asset($name['card']), 'checked' => 'checked'];
            } else {
                $creditCards[$name['text']] = ['value' => $card, 'data-imageUrl' => asset($name['card'])];
            }
        }

        $account->load('account_gateways');
        $currentGateways = $account->account_gateways;
        $gateways = Gateway::where('payment_library_id', '=', 1)->orderBy('name')->get();

        if ($accountGateway) {
            $accountGateway->fields = [];
        }

        foreach ($gateways as $gateway) {
            $fields = $gateway->getFields();
            if (! $gateway->isCustom()) {
                asort($fields);
            }
            $gateway->fields = $gateway->id == GATEWAY_WEPAY ? [] : $fields;
            if ($accountGateway && $accountGateway->gateway_id == $gateway->id) {
                $accountGateway->fields = $gateway->fields;
            }
        }

        return [
            'account' => $account,
            'user' => $user,
            'accountGateway' => $accountGateway,
            'config' => false,
            'gateways' => $gateways,
            'creditCardTypes' => $creditCards,
            'countGateways' => count($currentGateways),
        ];
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->accountGatewayService->bulk($ids, $action);

        Session::flash('message', trans("texts.{$action}d_account_gateway"));

        return Redirect::to('settings/' . ACCOUNT_PAYMENTS);
    }

    /**
     * Stores new account.
     *
     * @param mixed $accountGatewayPublicId
     */
    public function save($accountGatewayPublicId = false)
    {
        $gatewayId = Input::get('primary_gateway_id') ?: Input::get('secondary_gateway_id');
        $gateway = Gateway::findOrFail($gatewayId);

        $rules = [];
        $fields = $gateway->getFields();
        $optional = array_merge(Gateway::$hiddenFields, Gateway::$optionalFields);

        if ($gatewayId == GATEWAY_DWOLLA) {
            $optional = array_merge($optional, ['key', 'secret']);
        } elseif ($gatewayId == GATEWAY_STRIPE) {
            if (Utils::isNinjaDev()) {
                // do nothing - we're unable to acceptance test with StripeJS
            } else {
                $rules['publishable_key'] = 'required';
                $rules['enable_ach'] = 'boolean';
            }
        }

        if ($gatewayId != GATEWAY_WEPAY) {
            foreach ($fields as $field => $details) {
                if (! in_array($field, $optional)) {
                    if (strtolower($gateway->name) == 'beanstream') {
                        if (in_array($field, ['merchant_id', 'passCode'])) {
                            $rules[$gateway->id . '_' . $field] = 'required';
                        }
                    } else {
                        $rules[$gateway->id . '_' . $field] = 'required';
                    }
                }
            }
        }

        $creditcards = Input::get('creditCardTypes');
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('gateways/create?other_providers=' . ($gatewayId == GATEWAY_WEPAY ? 'false' : 'true'))
                ->withErrors($validator)
                ->withInput();
        } else {
            $account = Account::with('account_gateways')->findOrFail(Auth::user()->account_id);
            $oldConfig = null;

            if ($accountGatewayPublicId) {
                $accountGateway = AccountGateway::scope($accountGatewayPublicId)->firstOrFail();
                $oldConfig = $accountGateway->getConfig();
            } else {
                // check they don't already have an active gateway for this provider
                // TODO complete this
                $accountGateway = AccountGateway::scope()
                                    ->whereGatewayId($gatewayId)
                                    ->first();
                if ($accountGateway) {
                    Session::flash('error', trans('texts.gateway_exists'));

                    return Redirect::to("gateways/{$accountGateway->public_id}/edit");
                }

                $accountGateway = AccountGateway::createNew();
                $accountGateway->gateway_id = $gatewayId;

                if ($gatewayId == GATEWAY_WEPAY) {
                    if (! $this->setupWePay($accountGateway, $wepayResponse)) {
                        return $wepayResponse;
                    }
                    $oldConfig = $accountGateway->getConfig();
                }
            }

            $config = new stdClass();

            if ($gatewayId != GATEWAY_WEPAY) {
                foreach ($fields as $field => $details) {
                    $value = trim(Input::get($gateway->id . '_' . $field));
                    // if the new value is masked use the original value
                    if ($oldConfig && $value && $value === str_repeat('*', strlen($value))) {
                        $value = $oldConfig->$field;
                    }
                    if (! $value && ($field == 'testMode' || $field == 'developerMode')) {
                        // do nothing
                    } elseif ($gatewayId == GATEWAY_CUSTOM) {
                        $config->$field = strip_tags($value);
                    } else {
                        $config->$field = $value;
                    }
                }
            } elseif ($oldConfig) {
                $config = clone $oldConfig;
            }

            $publishableKey = trim(Input::get('publishable_key'));
            if ($publishableKey = str_replace('*', '', $publishableKey)) {
                $config->publishableKey = $publishableKey;
            } elseif ($oldConfig && property_exists($oldConfig, 'publishableKey')) {
                $config->publishableKey = $oldConfig->publishableKey;
            }

            $plaidClientId = trim(Input::get('plaid_client_id'));
            if (! $plaidClientId || $plaidClientId = str_replace('*', '', $plaidClientId)) {
                $config->plaidClientId = $plaidClientId;
            } elseif ($oldConfig && property_exists($oldConfig, 'plaidClientId')) {
                $config->plaidClientId = $oldConfig->plaidClientId;
            }

            $plaidSecret = trim(Input::get('plaid_secret'));
            if (! $plaidSecret || $plaidSecret = str_replace('*', '', $plaidSecret)) {
                $config->plaidSecret = $plaidSecret;
            } elseif ($oldConfig && property_exists($oldConfig, 'plaidSecret')) {
                $config->plaidSecret = $oldConfig->plaidSecret;
            }

            $plaidPublicKey = trim(Input::get('plaid_public_key'));
            if (! $plaidPublicKey || $plaidPublicKey = str_replace('*', '', $plaidPublicKey)) {
                $config->plaidPublicKey = $plaidPublicKey;
            } elseif ($oldConfig && property_exists($oldConfig, 'plaidPublicKey')) {
                $config->plaidPublicKey = $oldConfig->plaidPublicKey;
            }

            if ($gatewayId == GATEWAY_STRIPE || $gatewayId == GATEWAY_WEPAY) {
                $config->enableAch = boolval(Input::get('enable_ach'));
            }

            if ($gatewayId == GATEWAY_BRAINTREE) {
                $config->enablePayPal = boolval(Input::get('enable_paypal'));
            }

            $cardCount = 0;
            if ($creditcards) {
                foreach ($creditcards as $card => $value) {
                    $cardCount += intval($value);
                }
            }

            $accountGateway->accepted_credit_cards = $cardCount;
            $accountGateway->show_address = Input::get('show_address') ? true : false;
            $accountGateway->update_address = Input::get('update_address') ? true : false;
            $accountGateway->setConfig($config);

            if ($accountGatewayPublicId) {
                $accountGateway->save();
            } else {
                $account->account_gateways()->save($accountGateway);
            }

            if (isset($wepayResponse)) {
                return $wepayResponse;
            } else {
                $this->testGateway($accountGateway);

                if ($accountGatewayPublicId) {
                    $message = trans('texts.updated_gateway');
                    Session::flash('message', $message);

                    return Redirect::to("gateways/{$accountGateway->public_id}/edit");
                } else {
                    $message = trans('texts.created_gateway');
                    Session::flash('message', $message);

                    return Redirect::to('/settings/online_payments');
                }
            }
        }
    }

    private function testGateway($accountGateway)
    {
        $paymentDriver = $accountGateway->paymentDriver();
        $result = $paymentDriver->isValid();

        if ($result !== true) {
            Session::flash('error', $result . ' - ' . trans('texts.gateway_config_error'));
        }
    }

    protected function getWePayUpdateUri($accountGateway)
    {
        if ($accountGateway->gateway_id != GATEWAY_WEPAY) {
            return null;
        }

        $wepay = Utils::setupWePay($accountGateway);

        $update_uri_data = $wepay->request('account/get_update_uri', [
            'account_id' => $accountGateway->getConfig()->accountId,
            'mode' => 'iframe',
            'redirect_uri' => URL::to('/gateways'),
        ]);

        return $update_uri_data->uri;
    }

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
        ];

        if (WEPAY_ENABLE_CANADA) {
            $rules['country'] = 'required|in:US,CA';
        }

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
            ];

            if (WEPAY_ENABLE_CANADA) {
                $accountDetails['country'] = Input::get('country');

                if (Input::get('country') == 'CA') {
                    $accountDetails['currencies'] = ['CAD'];
                    $accountDetails['country_options'] = ['debit_opt_in' => boolval(Input::get('debit_cards'))];
                }
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
                'country' => WEPAY_ENABLE_CANADA ? Input::get('country') : 'US',
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

    public function resendConfirmation($publicId = false)
    {
        $accountGateway = AccountGateway::scope($publicId)->firstOrFail();

        if ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            try {
                $wepay = Utils::setupWePay($accountGateway);
                $wepay->request('user/send_confirmation', []);

                Session::flash('message', trans('texts.resent_confirmation_email'));
            } catch (\WePayException $e) {
                Session::flash('error', $e->getMessage());
            }
        }

        return Redirect::to("gateways/{$accountGateway->public_id}/edit");
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function savePaymentGatewayLimits()
    {
        $gateway_type_id = intval(Input::get('gateway_type_id'));
        $gateway_settings = AccountGatewaySettings::scope()->where('gateway_type_id', '=', $gateway_type_id)->first();

        if (! $gateway_settings) {
            $gateway_settings = AccountGatewaySettings::createNew();
            $gateway_settings->gateway_type_id = $gateway_type_id;
        }

        $gateway_settings->min_limit = Input::get('limit_min_enable') ? intval(Input::get('limit_min')) : null;
        $gateway_settings->max_limit = Input::get('limit_max_enable') ? intval(Input::get('limit_max')) : null;

        if ($gateway_settings->max_limit !== null && $gateway_settings->min_limit > $gateway_settings->max_limit) {
            $gateway_settings->max_limit = $gateway_settings->min_limit;
        }

        $gateway_settings->fill(Input::all());
        $gateway_settings->save();

        Session::flash('message', trans('texts.updated_settings'));

        return Redirect::to('settings/' . ACCOUNT_PAYMENTS);
    }

}
