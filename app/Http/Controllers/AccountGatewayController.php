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
use Utils;
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
        $data['hiddenFields'] = Gateway::$hiddenFields;
        $data['paymentTypeId'] = $accountGateway->getPaymentType();
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
     * Displays the form for account creation
     *
     */
    public function create()
    {
        $data = self::getViewModel();
        $data['url'] = 'gateways';
        $data['method'] = 'POST';
        $data['title'] = trans('texts.add_gateway');
        $data['selectGateways'] = Gateway::where('payment_library_id', '=', 1)->where('id', '!=', GATEWAY_PAYPAL_EXPRESS)->where('id', '!=', GATEWAY_PAYPAL_EXPRESS)->orderBy('name')->get();
        $data['hiddenFields'] = Gateway::$hiddenFields;

        return View::make('accounts.account_gateway', $data);
    }

    private function getViewModel($accountGateway = false)
    {
        $selectedCards = $accountGateway ? $accountGateway->accepted_credit_cards : 0;
        $account = Auth::user()->account;

        $paymentTypes = [];
        foreach (Gateway::$paymentTypes as $type) {
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

        foreach ($gateways as $gateway) {
            $fields = $gateway->getFields();
            asort($fields);
            $gateway->fields = $fields;
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
        $paymentType = Input::get('payment_type_id');
        $gatewayId = Input::get('gateway_id');

        if ($paymentType == PAYMENT_TYPE_PAYPAL) {
            $gatewayId = GATEWAY_PAYPAL_EXPRESS;
        } elseif ($paymentType == PAYMENT_TYPE_BITCOIN) {
            $gatewayId = GATEWAY_BITPAY;
        } elseif ($paymentType == PAYMENT_TYPE_DWOLLA) {
            $gatewayId = GATEWAY_DWOLLA;
        }

        if (!$gatewayId) {
            Session::flash('error', trans('validation.required', ['attribute' => 'gateway']));
            return Redirect::to('gateways/create')
                ->withInput();
        }

        $gateway = Gateway::findOrFail($gatewayId);
        $fields = $gateway->getFields();
        $optional = array_merge(Gateway::$hiddenFields, Gateway::$optionalFields);

        if ($gatewayId == GATEWAY_DWOLLA) {
            $optional = array_merge($optional, ['key', 'secret']);
        }

        foreach ($fields as $field => $details) {
            if (!in_array($field, $optional)) {
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
            $oldConfig = null;

            if ($accountGatewayPublicId) {
                $accountGateway = AccountGateway::scope($accountGatewayPublicId)->firstOrFail();
                $oldConfig = json_decode($accountGateway->config);
            } else {
                $accountGateway = AccountGateway::createNew();
                $accountGateway->gateway_id = $gatewayId;
            }

            $config = new stdClass();
            foreach ($fields as $field => $details) {
                $value = trim(Input::get($gateway->id.'_'.$field));
                // if the new value is masked use the original value
                if ($oldConfig && $value && $value === str_repeat('*', strlen($value))) {
                    $value = $oldConfig->$field;
                }
                if (!$value && ($field == 'testMode' || $field == 'developerMode')) {
                    // do nothing
                } else {
                    $config->$field = $value;
                }
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
            $accountGateway->config = json_encode($config);

            if ($accountGatewayPublicId) {
                $accountGateway->save();
            } else {
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

            return Redirect::to("gateways/{$accountGateway->public_id}/edit");
        }
    }

}
