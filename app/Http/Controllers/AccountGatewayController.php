<?php namespace App\Http\Controllers;
/*
|--------------------------------------------------------------------------
| Confide Controller Template
|--------------------------------------------------------------------------
|
| This is the default Confide controller template for controlling user
| authentication. Feel free to change to your needs.
|
*/

use Ninja\Repositories\AccountRepository;

class AccountGatewayController extends BaseController
{
    public function getDatatable()
    {
        $query = \DB::table('account_gateways')
                    ->join('gateways', 'gateways.id', '=', 'account_gateways.gateway_id')
                    ->where('account_gateways.deleted_at', '=', null)
                    ->where('account_gateways.account_id', '=', \Auth::user()->account_id)
                    ->select('account_gateways.public_id', 'gateways.name', 'account_gateways.deleted_at');

        return Datatable::query($query)
            ->addColumn('name', function ($model) { return link_to('gateways/'.$model->public_id.'/edit', $model->name); })
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

        $recommendedGateways = Gateway::remember(DEFAULT_QUERY_CACHE)
                ->where('recommended', '=', '1')
                ->orderBy('sort_order')
                ->get();
        $recommendedGatewayArray = array();

        foreach ($recommendedGateways as $recommendedGateway) {
            $arrayItem = array(
                'value' => $recommendedGateway->id,
                'other' => 'false',
                'data-imageUrl' => asset($recommendedGateway->getLogoUrl()),
                'data-siteUrl' => $recommendedGateway->site_url,
            );
            $recommendedGatewayArray[$recommendedGateway->name] = $arrayItem;
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

        $otherItem = array(
            'value' => 1000000,
            'other' => 'true',
            'data-imageUrl' => '',
            'data-siteUrl' => '',
        );
        $recommendedGatewayArray['Other Options'] = $otherItem;

        $account->load('account_gateways');
        $currentGateways = $account->account_gateways;
        $gateways = Gateway::where('payment_library_id', '=', 1)->orderBy('name');
        $onlyPayPal = false;
        if (!$accountGateway) {
            if (count($currentGateways) > 0) {
                $currentGateway = $currentGateways[0];
                if ($currentGateway->isPayPal()) {
                    $gateways->where('id', '!=', GATEWAY_PAYPAL_EXPRESS);
                } else {
                    $gateways->where('id', '=', GATEWAY_PAYPAL_EXPRESS);
                    $onlyPayPal = true;
                }
            }
        }
        $gateways = $gateways->get();

        foreach ($gateways as $gateway) {
            $paymentLibrary = $gateway->paymentlibrary;
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
            'account' => $account,
            'accountGateway' => $accountGateway,
            'config' => false,
            'gateways' => $gateways,
            'recommendedGateways' => $recommendedGatewayArray,
            'creditCardTypes' => $creditCards,
            'tokenBillingOptions' => $tokenBillingOptions,
            'showBreadcrumbs' => false,
            'onlyPayPal' => $onlyPayPal,
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
        $recommendedId = Input::get('recommendedGateway_id');

        $gatewayId = ($recommendedId == 1000000 ? Input::get('gateway_id') : $recommendedId);

        if (!$gatewayId) {
            Session::flash('error', trans('validation.required', ['attribute' => 'gateway']));
            return Redirect::to('gateways/create')
                ->withInput();
        }

        $gateway = Gateway::findOrFail($gatewayId);
        $paymentLibrary = $gateway->paymentlibrary;
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