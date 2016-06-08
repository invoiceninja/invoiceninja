<?php namespace App\Http\Controllers;

use Datatable;
use Input;
use Redirect;
use Request;
use Session;
use Utils;
use View;
use Validator;
use Omnipay;
use CreditCard;
use URL;
use Cache;
use App\Models\Invoice;
use App\Models\Invitation;
use App\Models\Client;
use App\Models\PaymentType;
use App\Models\License;
use App\Models\Payment;
use App\Models\Affiliate;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Services\PaymentService;

use App\Http\Requests\PaymentRequest;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;

class PaymentController extends BaseController
{
    protected $entityType = ENTITY_PAYMENT;

    public function __construct(PaymentRepository $paymentRepo, InvoiceRepository $invoiceRepo, AccountRepository $accountRepo, ContactMailer $contactMailer, PaymentService $paymentService)
    {
        // parent::__construct();

        $this->paymentRepo = $paymentRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->accountRepo = $accountRepo;
        $this->contactMailer = $contactMailer;
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'sortCol' => '6',
            'columns' => Utils::trans([
              'checkbox',
              'invoice',
              'client',
              'transaction_reference',
              'method',
              'payment_amount',
              'payment_date',
              ''
            ]),
        ));
    }

    public function getDatatable($clientPublicId = null)
    {
        return $this->paymentService->getDatatable($clientPublicId, Input::get('sSearch'));
    }

    public function create(PaymentRequest $request)
    {
        $invoices = Invoice::scope()
                    ->viewable()
                    ->where('is_recurring', '=', false)
                    ->where('is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->with('client', 'invoice_status')
                    ->orderBy('invoice_number')->get();

        $data = array(
            'clientPublicId' => Input::old('client') ? Input::old('client') : ($request->client_id ?: 0),
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : ($request->invoice_id ?: 0),
            'invoice' => null,
            'invoices' => $invoices,
            'payment' => null,
            'method' => 'POST',
            'url' => "payments",
            'title' => trans('texts.new_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'paymentTypeId' => Input::get('paymentTypeId'),
            'clients' => Client::scope()->viewable()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    public function edit(PaymentRequest $request)
    {
        $payment = $request->entity();

        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $data = array(
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->where('is_recurring', '=', false)->where('is_quote', '=', false)
                            ->with('client', 'invoice_status')->orderBy('invoice_number')->get(),
            'payment' => $payment,
            'method' => 'PUT',
            'url' => 'payments/'.$payment->public_id,
            'title' => trans('texts.edit_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    private function getLicensePaymentDetails($input, $affiliate)
    {
        $data = $this->paymentService->convertInputForOmnipay($input);
        $card = new CreditCard($data);

        return [
            'amount' => $affiliate->price,
            'card' => $card,
            'currency' => 'USD',
            'returnUrl' => URL::to('license_complete'),
            'cancelUrl' => URL::to('/')
        ];
    }

    public function show_payment($invitationKey, $paymentType = false)
    {

        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.account_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $client->account;
        $useToken = false;

        if ($paymentType) {
            $paymentType = 'PAYMENT_TYPE_' . strtoupper($paymentType);
        } else {
            $paymentType = Session::get($invitation->id . 'payment_type') ?:
                                $account->account_gateways[0]->getPaymentType();
        }

        if ($paymentType == PAYMENT_TYPE_TOKEN) {
            $useToken = true;
            $paymentType = PAYMENT_TYPE_CREDIT_CARD;
        }
        Session::put($invitation->id . 'payment_type', $paymentType);

        $accountGateway = $invoice->client->account->getGatewayByType($paymentType);
        $gateway = $accountGateway->gateway;

        $acceptedCreditCardTypes = $accountGateway->getCreditcardTypes();


        // Handle offsite payments
        if ($useToken || $paymentType != PAYMENT_TYPE_CREDIT_CARD
            || $gateway->id == GATEWAY_EWAY
            || $gateway->id == GATEWAY_TWO_CHECKOUT
            || $gateway->id == GATEWAY_PAYFAST
            || $gateway->id == GATEWAY_MOLLIE) {
            if (Session::has('error')) {
                Session::reflash();
                return Redirect::to('view/'.$invitationKey);
            } else {
                return self::do_payment($invitationKey, false, $useToken);
            }
        }

        $data = [
            'showBreadcrumbs' => false,
            'url' => 'payment/'.$invitationKey,
            'amount' => $invoice->getRequestedAmount(),
            'invoiceNumber' => $invoice->invoice_number,
            'client' => $client,
            'contact' => $invitation->contact,
            'gateway' => $gateway,
            'accountGateway' => $accountGateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Cache::get('countries'),
            'currencyId' => $client->getCurrencyId(),
            'currencyCode' => $client->currency ? $client->currency->code : ($account->currency ? $account->currency->code : 'USD'),
            'account' => $client->account,
            'hideLogo' => $account->hasFeature(FEATURE_WHITE_LABEL),
            'hideHeader' => $account->isNinjaAccount(),
            'clientViewCSS' => $account->clientViewCSS(),
            'clientFontUrl' => $account->getFontsUrl(),
            'showAddress' => $accountGateway->show_address,
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

        if (Input::has('product_id')) {
            Session::set('product_id', Input::get('product_id'));
        } else if (!Session::has('product_id')) {
            Session::set('product_id', PRODUCT_ONE_CLICK_INSTALL);
        }

        if (!Session::get('affiliate_id')) {
            return Utils::fatalError();
        }

        if (Utils::isNinjaDev() && Input::has('test_mode')) {
            Session::set('test_mode', Input::get('test_mode'));
        }

        $account = $this->accountRepo->getNinjaAccount();
        $account->load('account_gateways.gateway');
        $accountGateway = $account->getGatewayByType(PAYMENT_TYPE_CREDIT_CARD);
        $gateway = $accountGateway->gateway;
        $acceptedCreditCardTypes = $accountGateway->getCreditcardTypes();

        $affiliate = Affiliate::find(Session::get('affiliate_id'));

        $data = [
            'showBreadcrumbs' => false,
            'hideHeader' => true,
            'url' => 'license',
            'amount' => $affiliate->price,
            'client' => false,
            'contact' => false,
            'gateway' => $gateway,
            'account' => $account,
            'accountGateway' => $accountGateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Cache::get('countries'),
            'currencyId' => 1,
            'currencyCode' => 'USD',
            'paymentTitle' => $affiliate->payment_title,
            'paymentSubtitle' => $affiliate->payment_subtitle,
            'showAddress' => true,
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
            'country_id' => 'required',
        );

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return Redirect::to('license')
                ->withErrors($validator)
                ->withInput();
        }

        $account = $this->accountRepo->getNinjaAccount();
        $account->load('account_gateways.gateway');
        $accountGateway = $account->getGatewayByType(PAYMENT_TYPE_CREDIT_CARD);

        try {
            $affiliate = Affiliate::find(Session::get('affiliate_id'));

            if ($testMode) {
                $ref = 'TEST_MODE';
            } else {
                $gateway = $this->paymentService->createGateway($accountGateway);
                $details = self::getLicensePaymentDetails(Input::all(), $affiliate);
                $response = $gateway->purchase($details)->send();
                $ref = $response->getTransactionReference();

                if (!$response->isSuccessful() || !$ref) {
                    $this->error('License', $response->getMessage(), $accountGateway);
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
                'productId' => $license->product_id,
                'price' => $affiliate->price,
            ];

            $name = "{$license->first_name} {$license->last_name}";
            $this->contactMailer->sendLicensePaymentConfirmation($name, $license->email, $affiliate->price, $license->license_key, $license->product_id);

            if (Session::has('return_url')) {
                $data['redirectTo'] = Session::get('return_url')."?license_key={$license->license_key}&product_id=".Session::get('product_id');
                $data['message'] = "Redirecting to " . Session::get('return_url');
            }

            return View::make('public.license', $data);
        } catch (\Exception $e) {
            $this->error('License-Uncaught', false, $accountGateway, $e);
            return Redirect::to('license')->withInput();
        }
    }

    public function claim_license()
    {
        $licenseKey = Input::get('license_key');
        $productId = Input::get('product_id', PRODUCT_ONE_CLICK_INSTALL);

        $license = License::where('license_key', '=', $licenseKey)
                    ->where('is_claimed', '<', 5)
                    ->where('product_id', '=', $productId)
                    ->first();

        if ($license) {
            if ($license->transaction_reference != 'TEST_MODE') {
                $license->is_claimed = $license->is_claimed + 1;
                $license->save();
            }

            if ($productId == PRODUCT_INVOICE_DESIGNS) {
                return file_get_contents(storage_path() . '/invoice_designs.txt');
            } else {
                // temporary fix to enable previous version to work
                if (Input::get('get_date')) {
                    return $license->created_at->format('Y-m-d');
                } else {
                    return 'valid';
                }
            }
        } else {
            return RESULT_FAILURE;
        }
    }

    public function do_payment($invitationKey, $onSite = true, $useToken = false)
    {
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.currency', 'invoice.client.account.account_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $client->account;
        $accountGateway = $account->getGatewayByType(Session::get($invitation->id . 'payment_type'));


        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
        ];

        if ( ! Input::get('stripeToken')) {
            $rules = array_merge(
                $rules,
                [
                    'card_number' => 'required',
                    'expiration_month' => 'required',
                    'expiration_year' => 'required',
                    'cvv' => 'required',
                ]
            );
        }

        if ($accountGateway->show_address) {
            $rules = array_merge($rules, [
                'address1' => 'required',
                'city' => 'required',
                'state' => 'required',
                'postal_code' => 'required',
                'country_id' => 'required',
            ]);
        }

        if ($onSite) {
            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to('payment/'.$invitationKey)
                    ->withErrors($validator)
                    ->withInput(Request::except('cvv'));
            }

            if ($accountGateway->update_address) {
                $client->address1 = trim(Input::get('address1'));
                $client->address2 = trim(Input::get('address2'));
                $client->city = trim(Input::get('city'));
                $client->state = trim(Input::get('state'));
                $client->postal_code = trim(Input::get('postal_code'));
                $client->country_id = Input::get('country_id');
                $client->save();
            }
        }

        try {
            // For offsite payments send the client's details on file
            // If we're using a token then we don't need to send any other data
            if (!$onSite || $useToken) {
                $data = false;
            } else {
                $data = Input::all();
            }

            $gateway = $this->paymentService->createGateway($accountGateway);
            $details = $this->paymentService->getPaymentDetails($invitation, $accountGateway, $data);

            // check if we're creating/using a billing token
            if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
                if ($token = Input::get('stripeToken')) {
                    $details['token'] = $token;
                    unset($details['card']);
                }

                if ($useToken) {
                    $details['customerReference'] = $client->getGatewayToken();
                } elseif ($account->token_billing_type_id == TOKEN_BILLING_ALWAYS || Input::get('token_billing')) {
                    $token = $this->paymentService->createToken($gateway, $details, $accountGateway, $client, $invitation->contact_id);
                    if ($token) {
                        $details['customerReference'] = $token;
                    } else {
                        $this->error('Token-No-Ref', $this->paymentService->lastError, $accountGateway);
                        return Redirect::to('payment/'.$invitationKey)->withInput(Request::except('cvv'));
                    }
                }
            }

            $response = $gateway->purchase($details)->send();


            if ($accountGateway->gateway_id == GATEWAY_EWAY) {
                $ref = $response->getData()['AccessCode'];
            } elseif ($accountGateway->gateway_id == GATEWAY_TWO_CHECKOUT) {
                $ref = $response->getData()['cart_order_id'];
            } elseif ($accountGateway->gateway_id == GATEWAY_PAYFAST) {
                $ref = $response->getData()['m_payment_id'];
            } elseif ($accountGateway->gateway_id == GATEWAY_GOCARDLESS) {
                $ref = $response->getData()['signature'];
            } elseif ($accountGateway->gateway_id == GATEWAY_CYBERSOURCE) {
                $ref = $response->getData()['transaction_uuid'];
            } else {
                $ref = $response->getTransactionReference();
            }

            if (!$ref) {
                $this->error('No-Ref', $response->getMessage(), $accountGateway);

                if ($onSite) {
                    return Redirect::to('payment/'.$invitationKey)
                            ->withInput(Request::except('cvv'));
                } else {
                    return Redirect::to('view/'.$invitationKey);
                }
            }

            if ($response->isSuccessful()) {
                $payment = $this->paymentService->createPayment($invitation, $accountGateway, $ref);
                Session::flash('message', trans('texts.applied_payment'));

                if ($account->account_key == NINJA_ACCOUNT_KEY) {
                    Session::flash('trackEventCategory', '/account');
                    Session::flash('trackEventAction', '/buy_pro_plan');
                    Session::flash('trackEventAmount', $payment->amount);
                }

                return Redirect::to('view/'.$payment->invitation->invitation_key);
            } elseif ($response->isRedirect()) {

                $invitation->transaction_reference = $ref;
                $invitation->save();
                Session::put('transaction_reference', $ref);
                Session::save();
                $response->redirect();
            } else {
                $this->error('Unknown', $response->getMessage(), $accountGateway);
                if ($onSite) {
                    return Redirect::to('payment/'.$invitationKey)->withInput(Request::except('cvv'));
                } else {
                    return Redirect::to('view/'.$invitationKey);
                }
            }
        } catch (\Exception $e) {
            $this->error('Uncaught', false, $accountGateway, $e);
            if ($onSite) {
                return Redirect::to('payment/'.$invitationKey)->withInput(Request::except('cvv'));
            } else {
                return Redirect::to('view/'.$invitationKey);
            }
        }
    }

    public function offsite_payment()
    {
        $payerId = Request::query('PayerID');
        $token = Request::query('token');

        if (!$token) {
            $token = Session::pull('transaction_reference');
        }
        if (!$token) {
            return redirect(NINJA_WEB_URL);
        }

        $invitation = Invitation::with('invoice.client.currency', 'invoice.client.account.account_gateways.gateway')->where('transaction_reference', '=', $token)->firstOrFail();
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $client->account;

        if ($payerId) {
            $paymentType = PAYMENT_TYPE_PAYPAL;
        } else {
            $paymentType = Session::get($invitation->id . 'payment_type');
        }
        if (!$paymentType) {
            $this->error('No-Payment-Type', false, false);
            return Redirect::to($invitation->getLink());
        }
        $accountGateway = $account->getGatewayByType($paymentType);
        $gateway = $this->paymentService->createGateway($accountGateway);

        // Check for Dwolla payment error
        if ($accountGateway->isGateway(GATEWAY_DWOLLA) && Input::get('error')) {
            $this->error('Dwolla', Input::get('error_description'), $accountGateway);
            return Redirect::to($invitation->getLink());
        }

        // PayFast transaction referencce
        if ($accountGateway->isGateway(GATEWAY_PAYFAST) && Request::has('pt')) {
            $token = Request::query('pt');
        }

        try {
            if ($accountGateway->isGateway(GATEWAY_CYBERSOURCE)) {
                if (Input::get('decision') == 'ACCEPT') {
                    $payment = $this->paymentService->createPayment($invitation, $accountGateway, $token, $payerId);
                    Session::flash('message', trans('texts.applied_payment'));
                } else {
                    $message = Input::get('message') . ': ' . Input::get('invalid_fields');
                    Session::flash('error', $message);
                }
                return Redirect::to($invitation->getLink());
            } elseif (method_exists($gateway, 'completePurchase')
                && !$accountGateway->isGateway(GATEWAY_TWO_CHECKOUT)
                && !$accountGateway->isGateway(GATEWAY_CHECKOUT_COM)) {
                $details = $this->paymentService->getPaymentDetails($invitation, $accountGateway);

                $response = $this->paymentService->completePurchase($gateway, $accountGateway, $details, $token);

                $ref = $response->getTransactionReference() ?: $token;

                if ($response->isCancelled()) {
                    // do nothing
                } elseif ($response->isSuccessful()) {
                    $payment = $this->paymentService->createPayment($invitation, $accountGateway, $ref, $payerId);
                    Session::flash('message', trans('texts.applied_payment'));
                } else {
                    $this->error('offsite', $response->getMessage(), $accountGateway);
                }
                return Redirect::to($invitation->getLink());
            } else {
                $payment = $this->paymentService->createPayment($invitation, $accountGateway, $token, $payerId);
                Session::flash('message', trans('texts.applied_payment'));
                return Redirect::to($invitation->getLink());
            }
        } catch (\Exception $e) {
            $this->error('Offsite-uncaught', false, $accountGateway, $e);
            return Redirect::to($invitation->getLink());
        }
    }

    public function store(CreatePaymentRequest $request)
    {
        $input = $request->input();

        $input['invoice_id'] = Invoice::getPrivateId($input['invoice']);
        $input['client_id'] = Client::getPrivateId($input['client']);
        $payment = $this->paymentRepo->save($input);

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
            Session::flash('message', trans('texts.created_payment_emailed_client'));
        } else {
            Session::flash('message', trans('texts.created_payment'));
        }

        return redirect()->to($payment->client->getRoute());
    }

    public function update(UpdatePaymentRequest $request)
    {
        $payment = $this->paymentRepo->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_payment'));

        return redirect()->to($payment->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->paymentService->bulk($ids, $action);

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_payment', $count);
            Session::flash('message', $message);
        }

        return Redirect::to('payments');
    }

    private function error($type, $error, $accountGateway = false, $exception = false)
    {
        $message = '';
        if ($accountGateway && $accountGateway->gateway) {
            $message = $accountGateway->gateway->name . ': ';
        }
        $message .= $error ?: trans('texts.payment_error');

        Session::flash('error', $message);
        Utils::logError("Payment Error [{$type}]: " . ($exception ? Utils::getErrorString($exception) : $message), 'PHP', true);
    }
}
