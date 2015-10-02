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

class PaymentController extends BaseController
{
    public function __construct(PaymentRepository $paymentRepo, InvoiceRepository $invoiceRepo, AccountRepository $accountRepo, ContactMailer $contactMailer, PaymentService $paymentService)
    {
        parent::__construct();

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
            'columns' => Utils::trans(['checkbox', 'invoice', 'client', 'transaction_reference', 'method', 'payment_amount', 'payment_date', 'action']),
        ));
    }

    public function clientIndex()
    {
        $invitationKey = Session::get('invitation_key');
        if (!$invitationKey) {
            app()->abort(404);
        }

        $invitation = Invitation::with('account')->where('invitation_key', '=', $invitationKey)->first();
        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
            'color' => $color,
            'hideLogo' => $account->isWhiteLabel(),
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
              ->addColumn('payment_type', function ($model) { return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? $model->gateway_name : ''); });

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
        $invoices = Invoice::scope()
                    ->where('is_recurring', '=', false)
                    ->where('is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->with('client', 'invoice_status')
                    ->orderBy('invoice_number')->get();

        $data = array(
            'clientPublicId' => Input::old('client') ? Input::old('client') : $clientPublicId,
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : $invoicePublicId,
            'invoice' => null,
            'invoices' => $invoices,
            'payment' => null,
            'method' => 'POST',
            'url' => "payments",
            'title' => trans('texts.new_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
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
            $paymentType = Session::get('payment_type', $account->account_gateways[0]->getPaymentType());
        }
        if ($paymentType == PAYMENT_TYPE_TOKEN) {
            $useToken = true;
            $paymentType = PAYMENT_TYPE_CREDIT_CARD;
        }
        Session::put('payment_type', $paymentType);

        $accountGateway = $invoice->client->account->getGatewayByType($paymentType);
        $gateway = $accountGateway->gateway;
        $acceptedCreditCardTypes = $accountGateway->getCreditcardTypes();

        // Handle offsite payments
        if ($useToken || $paymentType != PAYMENT_TYPE_CREDIT_CARD || $gateway->id == GATEWAY_EWAY) {
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
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Cache::get('countries'),
            'currencyId' => $client->getCurrencyId(),
            'currencyCode' => $client->currency ? $client->currency->code : ($account->currency ? $account->currency->code : 'USD'),
            'account' => $client->account,
            'hideLogo' => $account->isWhiteLabel(),
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
        $accountGateway = $account->getGatewayByType(Session::get('payment_type'));
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
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'countries' => Cache::get('countries'),
            'currencyId' => 1,
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

            return $productId == PRODUCT_INVOICE_DESIGNS ? file_get_contents(storage_path() . '/invoice_designs.txt') : 'valid';
        } else {
            return 'invalid';
        }
    }

    public function do_payment($invitationKey, $onSite = true, $useToken = false)
    {
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.currency', 'invoice.client.account.account_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $client->account;
        $accountGateway = $account->getGatewayByType(Session::get('payment_type'));

        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'card_number' => 'required',
            'expiration_month' => 'required',
            'expiration_year' => 'required',
            'cvv' => 'required',
        ];

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
                    ->withInput();
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
            $details = $this->paymentService->getPaymentDetails($invitation, $data);

            // check if we're creating/using a billing token
            if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
                if ($useToken) {
                    $details['cardReference'] = $client->getGatewayToken();
                } elseif ($account->token_billing_type_id == TOKEN_BILLING_ALWAYS || Input::get('token_billing')) {
                    $token = $this->paymentService->createToken($gateway, $details, $accountGateway, $client, $invitation->contact_id);
                    if ($token) {
                        $details['cardReference'] = $token;
                    } else {
                        $this->error('Token-No-Ref', $this->paymentService->lastError, $accountGateway);
                        return Redirect::to('payment/'.$invitationKey)->withInput();
                    }
                }
            }

            $response = $gateway->purchase($details)->send();

            if ($accountGateway->gateway_id == GATEWAY_EWAY) {
                $ref = $response->getData()['AccessCode'];
                $token = $response->getCardReference();
            } else {
                $ref = $response->getTransactionReference();
            }

            if (!$ref) {
                $this->error('No-Ref', $response->getMessage(), $accountGateway);

                if ($onSite) {
                    return Redirect::to('payment/'.$invitationKey)->withInput();
                } else {
                    return Redirect::to('view/'.$invitationKey);
                }
            }

            if ($response->isSuccessful()) {
                $payment = $this->paymentService->createPayment($invitation, $ref);
                Session::flash('message', trans('texts.applied_payment'));

                if ($account->account_key == NINJA_ACCOUNT_KEY) {
                    Session::flash('trackEventCategory', '/account');
                    Session::flash('trackEventAction', '/buy_pro_plan');
                }

                return Redirect::to('view/'.$payment->invitation->invitation_key);
            } elseif ($response->isRedirect()) {
                $invitation->transaction_reference = $ref;
                $invitation->save();

                Session::put('transaction_reference', $ref);
                Session::save();
                $response->redirect();
            } else {
                $this->error('Fatal', $response->getMessage(), $accountGateway);
                return Utils::fatalError('Sorry, there was an error processing your payment. Please try again later.<p>', $response->getMessage());
            }
        } catch (\Exception $e) {
            $this->error('Uncaught', false, $accountGateway, $e);
            if ($onSite) {
                return Redirect::to('payment/'.$invitationKey)->withInput();
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

        $accountGateway = $invoice->client->account->getGatewayByType(Session::get('payment_type'));
        $gateway = $this->paymentService->createGateway($accountGateway);

        // Check for Dwolla payment error
        if ($accountGateway->isGateway(GATEWAY_DWOLLA) && Input::get('error')) {
            $this->error('Dwolla', Input::get('error_description'), $accountGateway);
            return Redirect::to('view/'.$invitation->invitation_key);
        }

        try {
            if (method_exists($gateway, 'completePurchase')) {
                $details = $this->paymentService->getPaymentDetails($invitation);
                $response = $gateway->completePurchase($details)->send();
                $ref = $response->getTransactionReference();

                if ($response->isSuccessful()) {
                    $payment = $this->paymentService->createPayment($invitation, $ref, $payerId);
                    Session::flash('message', trans('texts.applied_payment'));

                    return Redirect::to('view/'.$invitation->invitation_key);
                } else {
                    $this->error('offsite', $response->getMessage(), $accountGateway);
                    return Redirect::to('view/'.$invitation->invitation_key);
                }
            } else {
                $payment = $this->paymentService->createPayment($invitation, $token, $payerId);
                Session::flash('message', trans('texts.applied_payment'));

                return Redirect::to('view/'.$invitation->invitation_key);
            }
        } catch (\Exception $e) {
            $this->error('Offsite-uncaught', false, $accountGateway, $e);
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
            $payment = $this->paymentRepo->save($publicId, Input::all());

            if ($publicId) {
                Session::flash('message', trans('texts.updated_payment'));

                return Redirect::to('payments/');
            } else {
                if (Input::get('email_receipt')) {
                    $this->contactMailer->sendPaymentConfirmation($payment);
                    Session::flash('message', trans('texts.created_payment_emailed_client'));
                } else {
                    Session::flash('message', trans('texts.created_payment'));
                }

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

    private function error($type, $error, $accountGateway, $exception = false)
    {
        if (!$error) {
            if ($exception) {
                $error = $exception->getMessage();
            } else {
                $error = trans('texts.payment_error');
            }
        }

        $message = '';
        if ($accountGateway && $accountGateway->gateway) {
            $message = $accountGateway->gateway->name . ': ';
        }
        $message .= $error;

        Session::flash('error', $message);
        Utils::logError("Payment Error [{$type}]: " . ($exception ? Utils::getErrorString($exception) : $message));
    }
}
