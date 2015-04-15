<?php namespace App\Http\Controllers;

use Auth;
use Datatable;
use DB;
use Input;
use Redirect;
use Session;
use View;
use Validator;
use stdClass;
use URL;

use App\Models\Gateway;
use App\Models\Account;
use App\Models\AccountGateway;

use App\Ninja\Repositories\AccountRepository;

class AccountGatewayController extends BaseController
{
    public function getDatatable()
    {
        $query = DB::table('account_gateways')
                    ->join('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->where('account_gateways.deleted_at', '=', null)
                    ->where('account_gateways.account_id', '=', Auth::user()->account_id)
                    ->select('account_gateways.public_id', 'gateways.name', 'account_gateways.deleted_at', 'account_gateways.gateway_id');

        return Datatable::query($query)
            ->addColumn('name', function ($model) { return link_to('gateways/'.$model->public_id.'/edit', $model->name); })
            ->addColumn('payment_type', function ($model) { return Gateway::getPrettyPaymentType($model->gateway_id); })
            ->addColumn('dropdown', function ($model) {
              $actions = '<div class="btn-group tr-action" style="visibility:hidden;">
                  <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                    '.trans('texts.select').' <span class="caret"></span>
                  </button>
                  <ul class="dropdown-menu" role="menu">';

              if (!$model->deleted_at) {
                  $actions .= '<li><a href="'.URL::to('gateways/'.$model->public_id).'/edit">'.uctrans('texts.edit_gateway').'</a></li>
                               <li class="divider"></li>
                               <li><a href="javascript:deleteAccountGateway('.$model->public_id.')">'.uctrans('texts.delete_gateway').'</a></li>';
              }

               $actions .= '</ul>
              </div>';

              return $actions;
            })
            ->orderColumns(['name'])
            ->make();
    }

    public function edit($publicId)
    {
        $accountGateway = AccountGateway::scope($publicId)->firstOrFail();
        $config = $accountGateway->config;
        $selectedCards = $accountGateway->accepted_credit_cards;

        $configFields = json_decode($config);

        foreach ($configFields as $configField => $value) {
            $configFields->$configField = str_repeat('*', strlen($value));
        }

        $data = self::getViewModel($accountGateway);
        $data['url'] = 'gateways/'.$publicId;
        $data['method'] = 'PUT';
        $data['title'] = trans('texts.edit_gateway') . ' - ' . $accountGateway->gateway->name;
        $data['config'] = $configFields;
        $data['paymentTypeId'] = $accountGateway->getPaymentType();

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
     * Displays the form for account creation
     *
     */
    public function create()
    {
        $data = self::getViewModel();
        $data['url'] = 'gateways';
        $data['method'] = 'POST';
        $data['title'] = trans('texts.add_gateway');

        return View::make('accounts.account_gateway', $data);
    }

    private function getViewModel($accountGateway = false)
    {
        $selectedCards = $accountGateway ? $accountGateway->accepted_credit_cards : 0;
        $account = Auth::user()->account;

        $paymentTypes = [];
        foreach ([PAYMENT_TYPE_CREDIT_CARD, PAYMENT_TYPE_PAYPAL, PAYMENT_TYPE_BITCOIN] as $type) {
            if ($accountGateway || !$account->getGatewayByType($type)) {
                $paymentTypes[$type] = trans('texts.'.strtolower($type));

                if ($type == PAYMENT_TYPE_BITCOIN) {
                    $paymentTypes[$type] .= ' - BitPay';
                }
            }
        }

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
        $selectGateways = Gateway::where('payment_library_id', '=', 1)->where('id', '!=', GATEWAY_PAYPAL_EXPRESS)->where('id', '!=', GATEWAY_PAYPAL_EXPRESS)->orderBy('name')->get();

        foreach ($gateways as $gateway) {
            $gateway->fields = $gateway->getFields();
            if ($accountGateway && $accountGateway->gateway_id == $gateway->id) {
                $accountGateway->fields = $gateway->fields;
            }
        }

        $tokenBillingOptions = [];
        for ($i=1; $i<=4; $i++) {
            $tokenBillingOptions[$i] = trans("texts.token_billing_{$i}");
        }

        return [
            'paymentTypes' => $paymentTypes,
            'account' => $account,
            'accountGateway' => $accountGateway,
            'config' => false,
            'gateways' => $gateways,
            'selectGateways' => $selectGateways,
            'creditCardTypes' => $creditCards,
            'tokenBillingOptions' => $tokenBillingOptions,
            'showBreadcrumbs' => false,
            'countGateways' => count($currentGateways)
        ];
    }

    public function delete()
    {
        $accountGatewayPublicId = Input::get('accountGatewayPublicId');
        $gateway = AccountGateway::scope($accountGatewayPublicId)->firstOrFail();

        $gateway->delete();

        Session::flash('message', trans('texts.deleted_gateway'));

        return Redirect::to('company/payments');
    }

    /**
     * Stores new account
     *
     */
    public function save($accountGatewayPublicId = false)
    {
        $rules = array();
        $gatewayId = Input::get('gateway_id');

        if (!$gatewayId) {
            Session::flash('error', trans('validation.required', ['attribute' => 'gateway']));
            return Redirect::to('gateways/create')
                ->withInput();
        }

        $gateway = Gateway::findOrFail($gatewayId);
        $fields = $gateway->getFields();

        foreach ($fields as $field => $details) {
            if (!in_array($field, ['testMode', 'developerMode', 'headerImageUrl', 'solutionType', 'landingPage', 'brandName', 'logoImageUrl', 'borderColor'])) {
                if (strtolower($gateway->name) == 'beanstream') {
                    if (in_array($field, ['merchant_id', 'passCode'])) {
                        $rules[$gateway->id.'_'.$field] = 'required';
                    }
                } else {
                    $rules[$gateway->id.'_'.$field] = 'required';
                }
            }
        }

        $creditcards = Input::get('creditCardTypes');
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('gateways/create')
                ->withErrors($validator)
                ->withInput();
        } else {
            $account = Account::with('account_gateways')->findOrFail(Auth::user()->account_id);

            if ($accountGatewayPublicId) {
                $accountGateway = AccountGateway::scope($accountGatewayPublicId)->firstOrFail();
            } else {
                $accountGateway = AccountGateway::createNew();
                $accountGateway->gateway_id = $gatewayId;
            }

            $isMasked = false;

            $config = new stdClass();
            foreach ($fields as $field => $details) {
                $value = trim(Input::get($gateway->id.'_'.$field));

                if ($value && $value === str_repeat('*', strlen($value))) {
                    $isMasked = true;
                }

                $config->$field = $value;
            }

            $cardCount = 0;
            if ($creditcards) {
                foreach ($creditcards as $card => $value) {
                    $cardCount += intval($value);
                }
            }

            // if the values haven't changed don't update the config
            if ($isMasked && $accountGatewayPublicId) {
                $accountGateway->accepted_credit_cards = $cardCount;
                $accountGateway->save();
            // if there's an existing config for this gateway update it
            } elseif (!$isMasked && $accountGatewayPublicId && $accountGateway->gateway_id == $gatewayId) {
                $accountGateway->accepted_credit_cards = $cardCount;
                $accountGateway->config = json_encode($config);
                $accountGateway->save();
            // otherwise, create a new gateway config
            } else {
                $accountGateway->config = json_encode($config);
                $accountGateway->accepted_credit_cards = $cardCount;
                $account->account_gateways()->save($accountGateway);
            }

            if (Input::get('token_billing_type_id')) {
                $account->token_billing_type_id = Input::get('token_billing_type_id');
                $account->save();
            }

            if ($accountGatewayPublicId) {
                $message = trans('texts.updated_gateway');
            } else {
                $message = trans('texts.created_gateway');
            }

            Session::flash('message', $message);

            return Redirect::to('company/payments');
        }
    }

}