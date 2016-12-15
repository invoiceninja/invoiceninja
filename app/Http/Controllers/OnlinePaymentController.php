<?php namespace App\Http\Controllers;

use Session;
use Input;
use Utils;
use View;
use Auth;
use URL;
use Crawler;
use Exception;
use Validator;
use App\Models\Invitation;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use App\Ninja\Mailers\UserMailer;
use App\Http\Requests\CreateOnlinePaymentRequest;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Services\InvoiceService;
use App\Models\GatewayType;

/**
 * Class OnlinePaymentController
 */
class OnlinePaymentController extends BaseController
{
    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * @var UserMailer
     */
    protected $userMailer;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * OnlinePaymentController constructor.
     *
     * @param PaymentService $paymentService
     * @param UserMailer $userMailer
     */
    public function __construct(PaymentService $paymentService, UserMailer $userMailer, InvoiceRepository $invoiceRepo)
    {
        $this->paymentService = $paymentService;
        $this->userMailer = $userMailer;
        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * @param $invitationKey
     * @param bool $gatewayType
     * @param bool $sourceId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function showPayment($invitationKey, $gatewayTypeAlias = false, $sourceId = false)
    {
        if ( ! $invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return response()->view('error', [
                'error' => trans('texts.invoice_not_found'),
                'hideHeader' => true,
            ]);
        }

        if ( ! $invitation->invoice->canBePaid()) {
            return redirect()->to('view/' . $invitation->invitation_key);
        }

        $invitation = $invitation->load('invoice.client.account.account_gateways.gateway');
        $account = $invitation->account;
        $account->loadLocalizationSettings($invitation->invoice->client);

        if ( ! $gatewayTypeAlias) {
            $gatewayTypeId = Session::get($invitation->id . 'gateway_type');
        } elseif ($gatewayTypeAlias != GATEWAY_TYPE_TOKEN) {
            $gatewayTypeId = GatewayType::getIdFromAlias($gatewayTypeAlias);
        } else {
            $gatewayTypeId = $gatewayTypeAlias;
        }

        $paymentDriver = $account->paymentDriver($invitation, $gatewayTypeId);

        try {
            return $paymentDriver->startPurchase(Input::all(), $sourceId);
        } catch (Exception $exception) {
            return $this->error($paymentDriver, $exception);
        }
    }

    /**
     * @param CreateOnlinePaymentRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doPayment(CreateOnlinePaymentRequest $request)
    {
        $invitation = $request->invitation;
        $gatewayTypeId = Session::get($invitation->id . 'gateway_type');
        $paymentDriver = $invitation->account->paymentDriver($invitation, $gatewayTypeId);

        if ( ! $invitation->invoice->canBePaid()) {
            return redirect()->to('view/' . $invitation->invitation_key);
        }

        try {
            $paymentDriver->completeOnsitePurchase($request->all());

            if ($paymentDriver->isTwoStep()) {
                Session::flash('warning', trans('texts.bank_account_verification_next_steps'));
            } else {
                Session::flash('message', trans('texts.applied_payment'));
            }

            return $this->completePurchase($invitation);
        } catch (Exception $exception) {
            return $this->error($paymentDriver, $exception, true);
        }
    }

    /**
     * @param bool $invitationKey
     * @param mixed $gatewayTypeAlias
     * @return \Illuminate\Http\RedirectResponse
     */
    public function offsitePayment($invitationKey = false, $gatewayTypeAlias = false)
    {
        $invitationKey = $invitationKey ?: Session::get('invitation_key');
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.account_gateways.gateway')
                        ->where('invitation_key', '=', $invitationKey)->firstOrFail();

        if ( ! $gatewayTypeAlias) {
            $gatewayTypeId = Session::get($invitation->id . 'gateway_type');
        } elseif ($gatewayTypeAlias != GATEWAY_TYPE_TOKEN) {
            $gatewayTypeId = GatewayType::getIdFromAlias($gatewayTypeAlias);
        } else {
            $gatewayTypeId = $gatewayTypeAlias;
        }

        $paymentDriver = $invitation->account->paymentDriver($invitation, $gatewayTypeId);

        if ($error = Input::get('error_description') ?: Input::get('error')) {
            return $this->error($paymentDriver, $error);
        }

        try {
            if ($paymentDriver->completeOffsitePurchase(Input::all())) {
                Session::flash('message', trans('texts.applied_payment'));
            }
            return $this->completePurchase($invitation, true);
        } catch (Exception $exception) {
            return $this->error($paymentDriver, $exception);
        }
    }

    private function completePurchase($invitation, $isOffsite = false)
    {
        if ($redirectUrl = session('redirect_url:' . $invitation->invitation_key)) {
            $separator = strpos($redirectUrl, '?') === false ? '?' : '&';
            return redirect()->to($redirectUrl . $separator . 'invoice_id=' . $invitation->invoice->public_id);
        } else {
            // Allow redirecting to iFrame for offsite payments
            if ($isOffsite) {
                return redirect()->to($invitation->getLink());
            } else {
                return redirect()->to('view/' . $invitation->invitation_key);
            }
        }
    }

    /**
     * @param $paymentDriver
     * @param $exception
     * @param bool $showPayment
     * @return \Illuminate\Http\RedirectResponse
     */
    private function error($paymentDriver, $exception, $showPayment = false)
    {
        if (is_string($exception)) {
            $displayError = $exception;
            $logError = $exception;
        } else {
            $displayError = $exception->getMessage();
            $logError = Utils::getErrorString($exception);
        }

        $message = sprintf('%s: %s', ucwords($paymentDriver->providerName()), $displayError);
        Session::flash('error', $message);

        $message = sprintf('Payment Error [%s]: %s', $paymentDriver->providerName(), $logError);
        Utils::logError($message, 'PHP', true);

        $route = $showPayment ? 'payment/' : 'view/';
        return redirect()->to($route . $paymentDriver->invitation->invitation_key);
    }

    /**
     * @param $routingNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankInfo($routingNumber) {
        if (strlen($routingNumber) != 9 || !preg_match('/\d{9}/', $routingNumber)) {
            return response()->json([
                'message' => 'Invalid routing number',
            ], 400);
        }

        $data = PaymentMethod::lookupBankData($routingNumber);

        if (is_string($data)) {
            return response()->json([
                'message' => $data,
            ], 500);
        } elseif (!empty($data)) {
            return response()->json($data);
        }

        return response()->json([
            'message' => 'Bank not found',
        ], 404);
    }

    /**
     * @param $accountKey
     * @param $gatewayId
     * @return \Illuminate\Http\JsonResponse
     */
    public function handlePaymentWebhook($accountKey, $gatewayId)
    {
        $gatewayId = intval($gatewayId);

        $account = Account::where('accounts.account_key', '=', $accountKey)->first();

        if (!$account) {
            return response()->json([
                'message' => 'Unknown account',
            ], 404);
        }

        $accountGateway = $account->getGatewayConfig(intval($gatewayId));

        if (!$accountGateway) {
            return response()->json([
                'message' => 'Unknown gateway',
            ], 404);
        }

        $paymentDriver = $accountGateway->paymentDriver();

        try {
            $result = $paymentDriver->handleWebHook(Input::all());
            return response()->json(['message' => $result]);
        } catch (Exception $exception) {
            Utils::logError($exception->getMessage(), 'PHP');
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function handleBuyNow(ClientRepository $clientRepo, InvoiceService $invoiceService, $gatewayTypeAlias = false)
    {
        if (Crawler::isCrawler()) {
            return redirect()->to(NINJA_WEB_URL, 301);
        }

        $account = Account::whereAccountKey(Input::get('account_key'))->first();
        $redirectUrl = Input::get('redirect_url');
        $failureUrl = URL::previous();

        if ( ! $account || ! $account->enable_buy_now_buttons || ! $account->hasFeature(FEATURE_BUY_NOW_BUTTONS)) {
            return redirect()->to("{$failureUrl}/?error=invalid account");
        }

        Auth::onceUsingId($account->users[0]->id);
        $product = Product::scope(Input::get('product_id'))->first();

        if ( ! $product) {
            return redirect()->to("{$failureUrl}/?error=invalid product");
        }

        $rules = [
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'email' => 'email|string|max:100',
        ];

        $validator = Validator::make(Input::all(), $rules);
        if ($validator->fails()) {
            return redirect()->to("{$failureUrl}/?error=" . $validator->errors()->first());
        }

        $data = [
            'currency_id' => $account->currency_id,
            'contact' => Input::all()
        ];
        $client = $clientRepo->save($data);

        $data = [
            'client_id' => $client->id,
            'tax_rate1' => $account->default_tax_rate ? $account->default_tax_rate->rate : 0,
            'tax_name1' => $account->default_tax_rate ? $account->default_tax_rate->name : '',
            'invoice_items' => [[
                'product_key' => $product->product_key,
                'notes' => $product->notes,
                'cost' => $product->cost,
                'qty' => 1,
                'tax_rate1' => $product->default_tax_rate ? $product->default_tax_rate->rate : 0,
                'tax_name1' => $product->default_tax_rate ? $product->default_tax_rate->name : '',
            ]]
        ];
        $invoice = $invoiceService->save($data);
        $invitation = $invoice->invitations[0];
        $link = $invitation->getLink();

        if ($redirectUrl) {
            session(['redirect_url:' . $invitation->invitation_key => $redirectUrl]);
        }

        if ($gatewayTypeAlias) {
            return redirect()->to($invitation->getLink('payment') . "/{$gatewayTypeAlias}");
        } else {
            return redirect()->to($invitation->getLink());
        }
    }
}
