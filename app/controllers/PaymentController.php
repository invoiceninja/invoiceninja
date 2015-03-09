<?php

use ninja\repositories\PaymentRepository;
use ninja\repositories\InvoiceRepository;
use ninja\repositories\AccountRepository;
use ninja\mailers\ContactMailer;

class PaymentController extends \BaseController
{
    protected $creditRepo;

    public function __construct(PaymentRepository $paymentRepo, InvoiceRepository $invoiceRepo, AccountRepository $accountRepo, ContactMailer $contactMailer)
    {
        parent::__construct();

        $this->paymentRepo = $paymentRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->accountRepo = $accountRepo;
        $this->contactMailer = $contactMailer;
    }

    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'columns' => Utils::trans(['checkbox', 'invoice', 'client', 'transaction_reference', 'method', 'payment_amount', 'payment_date', 'action']),
        ));
    }

    public function clientIndex()
    {
        $invitationKey = Session::get('invitation_key');
        if (!$invitationKey) {
            return Redirect::to('/setup');
        }

        $invitation = Invitation::with('account')->where('invitation_key', '=', $invitationKey)->first();
        $color = $invitation->account->primary_color ? $invitation->account->primary_color : '#0b4d78';
        
        $data = [
            'color' => $color,
            'hideLogo' => Session::get('white_label'),
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'columns' => Utils::trans(['invoice', 'transaction_reference', 'method', 'payment_amount', 'payment_date'])
        ];

        return View::make('public_list', $data);
    }

    public function getDatatable($clientPublicId = null)
    {
        $payments = $this->paymentRepo->find($clientPublicId, Input::get('sSearch'));
        $table = Datatable::query($payments);

        if (!$clientPublicId) {
            $table->addColumn('checkbox', function ($model) { return '<input type="checkbox" name="ids[]" value="'.$model->public_id.'" '.Utils::getEntityRowClass($model).'>'; });
        }

        $table->addColumn('invoice_number', function ($model) { return $model->invoice_public_id ? link_to('invoices/'.$model->invoice_public_id.'/edit', $model->invoice_number, ['class' => Utils::getEntityRowClass($model)]) : ''; });

        if (!$clientPublicId) {
            $table->addColumn('client_name', function ($model) { return link_to('clients/'.$model->client_public_id, Utils::getClientDisplayName($model)); });
        }

        $table->addColumn('transaction_reference', function ($model) { return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>'; })
              ->addColumn('payment_type', function ($model) { return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? '<i>Online payment</i>' : ''); });

        return $table->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
            ->addColumn('payment_date', function ($model) { return Utils::dateToString($model->payment_date); })
            ->addColumn('dropdown', function ($model) {
                if ($model->is_deleted || $model->invoice_is_deleted) {
                    return '<div style="height:38px"/>';
                }

                $str = '<div class="btn-group tr-action" style="visibility:hidden;">
                            <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                            '.trans('texts.select').' <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu">';

                if (!$model->deleted_at || $model->deleted_at == '0000-00-00') {
                    $str .= '<li><a href="payments/'.$model->public_id.'/edit">'.trans('texts.edit_payment').'</a></li>
                             <li class="divider"></li>
                             <li><a href="javascript:archiveEntity('.$model->public_id.')">'.trans('texts.archive_payment').'</a></li>';
                } else {
                    $str .= '<li><a href="javascript:restoreEntity('.$model->public_id.')">'.trans('texts.restore_payment').'</a></li>';
                }

                return $str.'<li><a href="javascript:deleteEntity('.$model->public_id.')">'.trans('texts.delete_payment').'</a></li></ul>
                        </div>';
            })
            ->make();
    }

    public function getClientDatatable()
    {
        $search = Input::get('sSearch');
        $invitationKey = Session::get('invitation_key');
        $invitation = Invitation::where('invitation_key', '=', $invitationKey)->with('contact.client')->first();

        if (!$invitation) {
            return [];
        }

        $invoice = $invitation->invoice;

        if (!$invoice || $invoice->is_deleted) {
            return [];
        }

        $payments = $this->paymentRepo->findForContact($invitation->contact->id, Input::get('sSearch'));

        return Datatable::query($payments)
                ->addColumn('invoice_number', function ($model) { return $model->invitation_key ? link_to('/view/'.$model->invitation_key, $model->invoice_number) : $model->invoice_number; })
                ->addColumn('transaction_reference', function ($model) { return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>'; })
                ->addColumn('payment_type', function ($model) { return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? '<i>Online payment</i>' : ''); })
                ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
                ->addColumn('payment_date', function ($model) { return Utils::dateToString($model->payment_date); })
                ->make();
    }

    public function create($clientPublicId = 0, $invoicePublicId = 0)
    {
        $data = array(
            'clientPublicId' => Input::old('client') ? Input::old('client') : $clientPublicId,
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : $invoicePublicId,
            'invoice' => null,
            'invoices' => Invoice::scope()->where('is_recurring', '=', false)->where('is_quote', '=', false)
                            ->with('client', 'invoice_status')->orderBy('invoice_number')->get(),
            'payment' => null,
            'method' => 'POST',
            'url' => "payments",
            'title' => trans('texts.new_payment'),
            //'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'paymentTypes' => PaymentType::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    public function edit($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();
        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $data = array(
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->where('is_recurring', '=', false)->where('is_quote', '=', false)
                            ->with('client', 'invoice_status')->orderBy('invoice_number')->get(),
            'payment' => $payment,
            'method' => 'PUT',
            'url' => 'payments/'.$publicId,
            'title' => trans('texts.edit_payment'),
            //'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'paymentTypes' => PaymentType::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    private function createGateway($accountGateway)
    {
        $gateway = Omnipay::create($accountGateway->gateway->provider);
        $config = json_decode($accountGateway->config);

        /*
        $gateway->setSolutionType("Sole");
        $gateway->setLandingPage("Billing");
        */

        foreach ($config as $key => $val) {
            if (!$val) {
                continue;
            }

            $function = "set".ucfirst($key);
            $gateway->$function($val);
        }

        if (Utils::isNinjaDev()) {
            $gateway->setTestMode(true);
        }

        return $gateway;
    }

    private function getLicensePaymentDetails($input, $affiliate)
    {
        $data = self::convertInputForOmnipay($input);
        $card = new CreditCard($data);

        return [
            'amount' => $affiliate->price,
            'card' => $card,
            'currency' => 'USD',
            'returnUrl' => URL::to('license_complete'),
            'cancelUrl' => URL::to('/')
        ];
    }

    private function convertInputForOmnipay($input)
    {
        return [
            'firstName' => $input['first_name'],
            'lastName' => $input['last_name'],
            'number' => $input['card_number'],
            'expiryMonth' => $input['expiration_month'],
            'expiryYear' => $input['expiration_year'],
            'cvv' => $input['cvv'],
            'billingAddress1' => $input['address1'],
            'billingAddress2' => $input['address2'],
            'billingCity' => $input['city'],
            'billingState' => $input['state'],
            'billingPostcode' => $input['postal_code'],
            'shippingAddress1' => $input['address1'],
            'shippingAddress2' => $input['address2'],
            'shippingCity' => $input['city'],
            'shippingState' => $input['state'],
            'shippingPostcode' => $input['postal_code']
        ];
    }

    private function getPaymentDetails($invitation, $input = null)
    {
        $invoice = $invitation->invoice;
        $key = $invoice->invoice_number.'_details';
        $gateway = $invoice->client->account->getGatewayByType(Session::get('payment_type'))->gateway;
        $paymentLibrary = $gateway->paymentlibrary;
        $currencyCode = $invoice->client->currency ? $invoice->client->currency->code : ($invoice->account->currency ? $invoice->account->currency->code : 'USD');

        if ($input && $paymentLibrary->id == PAYMENT_LIBRARY_OMNIPAY) {
            $data = self::convertInputForOmnipay($input);

            Session::put($key, $data);
        } elseif ($input && $paymentLibrary->id == PAYMENT_LIBRARY_PHP_PAYMENTS) {
            $input = Input::all();
            $data = [
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'cc_number' => $input['card_number'],
                'cc_exp' => $input['expiration_month'].$input['expiration_year'],
                'cc_code' => $input['cvv'],
                'street' => $input['address1'],
                'street2' => $input['address2'],
                'city' => $input['city'],
                'state' => $input['state'],
                'postal_code' => $input['postal_code'],
                'amt' => $invoice->balance,
                'ship_to_street' => $input['address1'],
                'ship_to_city' => $input['city'],
                'ship_to_state' => $input['state'],
                'ship_to_postal_code' => $input['postal_code'],
                'currency_code' => $currencyCode,
            ];

            switch ($gateway->id) {
                case GATEWAY_BEANSTREAM:
                    $data['phone'] = $input['phone'];
                    $data['email'] = $input['email'];
                    $data['country'] = $input['country'];
                    $data['ship_to_country'] = $input['country'];
                    break;
                case GATEWAY_BRAINTREE:
                    $data['ship_to_state'] = 'Ohio'; //$input['state'];
                    break;
            }

            if (strlen($data['cc_exp']) == 5) {
                $data['cc_exp'] = '0'.$data['cc_exp'];
            }

            Session::put($key, $data);

            return $data;
        } elseif (Session::get($key)) {
            $data = Session::get($key);
        } else {
            $data = [];
        }

        if ($paymentLibrary->id == PAYMENT_LIBRARY_OMNIPAY) {
            $card = new CreditCard($data);

            return [
                'amount' => $invoice->balance,
                'card' => $card,
                'currency' => $currencyCode,
                'returnUrl' => URL::to('complete'),
                'cancelUrl' => $invitation->getLink(),
                'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}",
            ];
        } else {
            return $data;
        }
    }

    public function show_payment($invitationKey)
    {
        // Handle token billing
        if (Input::get('use_token') == 'true') {
            return self::do_payment($invitationKey, false, true);
        }

        if (Input::has('use_paypal')) {
            Session::put('payment_type', Input::get('use_paypal') == 'true' ? PAYMENT_TYPE_PAYPAL : PAYMENT_TYPE_CREDIT_CARD);
        } elseif (!Session::has('payment_type')) {
            Session::put('payment_type', PAYMENT_TYPE_ANY);
        }

        // For PayPal we redirect straight to their site
        $usePayPal = false;
        if ($usePayPal = Input::get('use_paypal')) {
            $usePayPal = $usePayPal == 'true';
        } else {
            $invitation = Invitation::with('invoice.client.account', 'invoice.client.account.account_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
            $account = $invitation->invoice->client->account;
            if (count($account->account_gateways) == 1 && $account->getGatewayByType(PAYMENT_TYPE_PAYPAL)) {
                $usePayPal = true;
            }
        }
        if ($usePayPal) {
            if (Session::has('error')) {
                Session::reflash();
                return Redirect::to('view/'.$invitationKey);
            } else {
                return self::do_payment($invitationKey, false);
            }
        }

        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.account_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $accountGateway = $invoice->client->account->getGatewayByType(Session::get('payment_type'));
        $gateway = $invoice->client->account->getGatewayByType(Session::get('payment_type'))->gateway;
        $paymentLibrary = $gateway->paymentlibrary;
        $acceptedCreditCardTypes = $accountGateway->getCreditcardTypes();

        $data = [
            'showBreadcrumbs' => false,
            'url' => 'payment/'.$invitationKey,
            'amount' => $invoice->balance,
            'invoiceNumber' => $invoice->invoice_number,
            'client' => $client,
            'contact' => $invitation->contact,
            'paymentLibrary' => $paymentLibrary,
            'gateway' => $gateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Country::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'currencyId' => $client->currency_id,
            'account' => $client->account
        ];

        return View::make('payments.payment', $data);
    }

    public function show_license_payment()
    {
        if (Input::has('return_url')) {
            Session::set('return_url', Input::get('return_url'));
        }

        if (Input::has('affiliate_key')) {
            if ($affiliate = Affiliate::where('affiliate_key', '=', Input::get('affiliate_key'))->first()) {
                Session::set('affiliate_id', $affiliate->id);
            }
        }

        Session::set('product_id', Input::get('product_id', PRODUCT_ONE_CLICK_INSTALL));

        if (!Session::get('affiliate_id')) {
            return Utils::fatalError();
        }

        if (Utils::isNinjaDev() && Input::has('test_mode')) {
            Session::set('test_mode', Input::get('test_mode'));
        }

        $account = $this->accountRepo->getNinjaAccount();
        $account->load('account_gateways.gateway');
        $accountGateway = $account->getGatewayByType(Session::get('payment_type'));
        $gateway = $accountGateway->gateway;
        $paymentLibrary = $gateway->paymentlibrary;
        $acceptedCreditCardTypes = $accountGateway->getCreditcardTypes();

        $affiliate = Affiliate::find(Session::get('affiliate_id'));

        $data = [
            'showBreadcrumbs' => false,
            'hideHeader' => true,
            'url' => 'license',
            'amount' => $affiliate->price,
            'client' => false,
            'contact' => false,
            'paymentLibrary' => $paymentLibrary,
            'gateway' => $gateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Country::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'currencyId' => 1,
            'paymentTitle' => $affiliate->payment_title,
            'paymentSubtitle' => $affiliate->payment_subtitle,
        ];

        return View::make('payments.payment', $data);
    }

    public function do_license_payment()
    {
        $testMode = Session::get('test_mode') === 'true';

        $rules = array(
            'first_name' => 'required',
            'last_name' => 'required',
            'card_number' => 'required',
            'expiration_month' => 'required',
            'expiration_year' => 'required',
            'cvv' => 'required',
            'address1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'postal_code' => 'required',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('license')
                ->withErrors($validator);
        }

        $account = $this->accountRepo->getNinjaAccount();
        $account->load('account_gateways.gateway');
        $accountGateway = $account->getGatewayByType(PAYMENT_TYPE_CREDIT_CARD);

        try {
            $affiliate = Affiliate::find(Session::get('affiliate_id'));

            if ($testMode) {
                $ref = 'TEST_MODE';
            } else {
                $gateway = self::createGateway($accountGateway);
                $details = self::getLicensePaymentDetails(Input::all(), $affiliate);
                $response = $gateway->purchase($details)->send();
                $ref = $response->getTransactionReference();

                if (!$ref) {
                    Session::flash('error', $response->getMessage());

                    return Redirect::to('license')->withInput();
                }

                if (!$response->isSuccessful()) {
                    Session::flash('error', $response->getMessage());
                    Utils::logError($response->getMessage());

                    return Redirect::to('license')->withInput();
                }
            }

            $licenseKey = Utils::generateLicense();

            $license = new License();
            $license->first_name = Input::get('first_name');
            $license->last_name = Input::get('last_name');
            $license->email = Input::get('email');
            $license->transaction_reference = $ref;
            $license->license_key = $licenseKey;
            $license->affiliate_id = Session::get('affiliate_id');
            $license->product_id = Session::get('product_id');
            $license->save();

            $data = [
                'message' => $affiliate->payment_subtitle,
                'license' => $licenseKey,
                'hideHeader' => true,
            ];

            $name = "{$license->first_name} {$license->last_name}";
            $this->contactMailer->sendLicensePaymentConfirmation($name, $license->email, $affiliate->price, $license->license_key, $license->product_id);

            if (Session::has('return_url')) {
                return Redirect::away(Session::get('return_url')."?license_key={$license->license_key}&product_id=".Session::get('product_id'));
            } else {
                return View::make('public.license', $data);
            }
        } catch (\Exception $e) {
            $errorMessage = trans('texts.payment_error');
            Session::flash('error', $errorMessage);
            Utils::logError(Utils::getErrorString($e));

            return Redirect::to('license')->withInput();
        }
    }

    public function claim_license()
    {
        $licenseKey = Input::get('license_key');
        $productId = Input::get('product_id', PRODUCT_ONE_CLICK_INSTALL);

        $license = License::where('license_key', '=', $licenseKey)
                    ->where('is_claimed', '=', false)
                    ->where('product_id', '=', $productId)
                    ->first();

        if ($license) {
            if ($license->transaction_reference != 'TEST_MODE') {
                $license->is_claimed = true;
                $license->save();
            }

            return $productId == PRODUCT_INVOICE_DESIGNS ? $_ENV['INVOICE_DESIGNS'] : 'valid';
        } else {
            return 'invalid';
        }
    }

    public function do_payment($invitationKey, $onSite = true, $useToken = false)
    {
        $rules = array(
            'first_name' => 'required',
            'last_name' => 'required',
            'card_number' => 'required',
            'expiration_month' => 'required',
            'expiration_year' => 'required',
            'cvv' => 'required',
            'address1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'postal_code' => 'required',
        );

        if ($onSite) {
            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('payment/'.$invitationKey)
                    ->withErrors($validator);
            }
        }

        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.account_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $client->account;
        $accountGateway = $account->getGatewayByType(Session::get('payment_type'));
        $paymentLibrary = $accountGateway->gateway->paymentlibrary;

        if ($onSite) {
            $client->address1 = trim(Input::get('address1'));
            $client->address2 = trim(Input::get('address2'));
            $client->city = trim(Input::get('city'));
            $client->state = trim(Input::get('state'));
            $client->postal_code = trim(Input::get('postal_code'));
            $client->save();
        }

        try {
            if ($paymentLibrary->id == PAYMENT_LIBRARY_OMNIPAY) {
                $gateway = self::createGateway($accountGateway);
                $details = self::getPaymentDetails($invitation, $useToken || !$onSite ? false : Input::all());
                
                if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
                    if ($useToken) {
                        $details['cardReference'] = $client->getGatewayToken();
                    } elseif ($account->token_billing_type_id == TOKEN_BILLING_ALWAYS || Input::get('token_billing')) {
                        $tokenResponse = $gateway->createCard($details)->send();
                        $cardReference = $tokenResponse->getCardReference();
                        $details['cardReference'] = $cardReference;

                        $token = AccountGatewayToken::where('client_id', '=', $client->id)
                                    ->where('account_gateway_id', '=', $accountGateway->id)->first();

                        if (!$token) {
                            $token = new AccountGatewayToken();
                            $token->account_id = $account->id;
                            $token->contact_id = $invitation->contact_id;
                            $token->account_gateway_id = $accountGateway->id;
                            $token->client_id = $client->id;
                        }

                        $token->token = $cardReference;
                        $token->save();
                    }
                }
                
                $response = $gateway->purchase($details)->send();
                $ref = $response->getTransactionReference();

                if (!$ref) {
                    
                    Session::flash('error', $response->getMessage());

                    if ($onSite) {
                        return Redirect::to('payment/'.$invitationKey)->withInput();
                    } else {
                        return Redirect::to('view/'.$invitationKey);
                    }
                }

                if ($response->isSuccessful()) {
                    $payment = self::createPayment($invitation, $ref);
                    Session::flash('message', trans('texts.applied_payment'));

                    return Redirect::to('view/'.$payment->invitation->invitation_key);
                } elseif ($response->isRedirect()) {
                    $invitation->transaction_reference = $ref;
                    $invitation->save();

                    Session::save();
                    $response->redirect();
                } else {
                    Session::flash('error', $response->getMessage());

                    return Utils::fatalError('Sorry, there was an error processing your payment. Please try again later.<p>', $response->getMessage());
                }
            }
        } catch (\Exception $e) {
            $errorMessage = trans('texts.payment_error');
            Session::flash('error', $errorMessage."<p>".$e->getMessage());
            Utils::logError(Utils::getErrorString($e));

            if ($onSite) {
                return Redirect::to('payment/'.$invitationKey)->withInput();
            } else {
                return Redirect::to('view/'.$invitationKey);
            }
        }
    }

    private function createPayment($invitation, $ref, $payerId = null)
    {
        $invoice = $invitation->invoice;
        $accountGateway = $invoice->client->account->getGatewayByType(Session::get('payment_type'));

        if ($invoice->account->account_key == NINJA_ACCOUNT_KEY) {
            $account = Account::find($invoice->client->public_id);
            $account->pro_plan_paid = date_create()->format('Y-m-d');
            $account->save();
        }

        $payment = Payment::createNew($invitation);
        $payment->invitation_id = $invitation->id;
        $payment->account_gateway_id = $accountGateway->id;
        $payment->invoice_id = $invoice->id;
        $payment->amount = $invoice->balance;
        $payment->client_id = $invoice->client_id;
        $payment->contact_id = $invitation->contact_id;
        $payment->transaction_reference = $ref;
        $payment->payment_date = date_create()->format('Y-m-d');

        if ($payerId) {
            $payment->payer_id = $payerId;
        }

        $payment->save();

        Event::fire('invoice.paid', $payment);

        return $payment;
    }

    public function offsite_payment()
    {
        $payerId = Request::query('PayerID');
        $token = Request::query('token');

        $invitation = Invitation::with('invoice.client.currency', 'invoice.client.account.account_gateways.gateway')->where('transaction_reference', '=', $token)->firstOrFail();
        $invoice = $invitation->invoice;

        $accountGateway = $invoice->client->account->getGatewayByType(Session::get('payment_type'));
        $gateway = self::createGateway($accountGateway);

        try {
            $details = self::getPaymentDetails($invitation);
            $response = $gateway->completePurchase($details)->send();
            $ref = $response->getTransactionReference();

            if ($response->isSuccessful()) {
                $payment = self::createPayment($invitation, $ref, $payerId);

                Session::flash('message', trans('texts.applied_payment'));

                return Redirect::to('view/'.$invitation->invitation_key);
            } else {
                $errorMessage = trans('texts.payment_error')."\n\n".$response->getMessage();
                Session::flash('error', $errorMessage);
                Utils::logError($errorMessage);

                return Redirect::to('view/'.$invitation->invitation_key);
            }
        } catch (\Exception $e) {
            $errorMessage = trans('texts.payment_error');
            Session::flash('error', $errorMessage);
            Utils::logError($errorMessage."\n\n".$e->getMessage());

            return Redirect::to('view/'.$invitation->invitation_key);
        }
    }

    public function store()
    {
        return $this->save();
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    private function save($publicId = null)
    {
        if (!$publicId && $errors = $this->paymentRepo->getErrors(Input::all())) {
            $url = $publicId ? 'payments/'.$publicId.'/edit' : 'payments/create';

            return Redirect::to($url)
                ->withErrors($errors)
                ->withInput();
        } else {
            $this->paymentRepo->save($publicId, Input::all());

            if ($publicId) {
                Session::flash('message', trans('texts.updated_payment'));

                return Redirect::to('payments/');
            } else {
                Session::flash('message', trans('texts.created_payment'));

                return Redirect::to('clients/'.Input::get('client'));
            }
        }
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('id') ? Input::get('id') : Input::get('ids');
        $count = $this->paymentRepo->bulk($ids, $action);

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_payment', $count);
            Session::flash('message', $message);
        }

        return Redirect::to('payments');
    }
}
