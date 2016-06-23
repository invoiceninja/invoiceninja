<?php namespace App\Http\Controllers;

use Session;
use Input;
use Request;
use Utils;
use View;
use Validator;
use Cache;
use Exception;
use App\Models\Invitation;
use App\Models\Account;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use App\Ninja\Mailers\UserMailer;
use App\Http\Requests\CreateOnlinePaymentRequest;

class OnlinePaymentController extends BaseController
{
    public function __construct(PaymentService $paymentService, UserMailer $userMailer)
    {
        $this->paymentService = $paymentService;
        $this->userMailer = $userMailer;
    }

    public function showPayment($invitationKey, $gatewayType = false, $sourceId = false)
    {
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.account_gateways.gateway')
                        ->where('invitation_key', '=', $invitationKey)->firstOrFail();

        if ( ! $gatewayType) {
            $gatewayType = Session::get($invitation->id . 'gateway_type');
        }

        $paymentDriver = $invitation->account->paymentDriver($invitation, $gatewayType);

        try {
            return $paymentDriver->startPurchase(Input::all(), $sourceId);
        } catch (Exception $exception) {
            return $this->error($paymentDriver, $exception);
        }
    }

    public function doPayment(CreateOnlinePaymentRequest $request)
    {
        $invitation = $request->invitation;
        $gatewayType = Session::get($invitation->id . 'gateway_type');
        $paymentDriver = $invitation->account->paymentDriver($invitation, $gatewayType);

        try {
            $paymentDriver->completeOnsitePurchase($request->all());

            if ($paymentDriver->isTwoStep()) {
                Session::flash('warning', trans('texts.bank_account_verification_next_steps'));
            } else {
                Session::flash('message', trans('texts.applied_payment'));
            }
            return redirect()->to('view/' . $invitation->invitation_key);
        } catch (Exception $exception) {
            return $this->error($paymentDriver, $exception, true);
        }
    }

    public function offsitePayment($invitationKey = false, $gatewayType = false)
    {
        $invitationKey = $invitationKey ?: Session::get('invitation_key');
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.client.currency', 'invoice.client.account.account_gateways.gateway')
                        ->where('invitation_key', '=', $invitationKey)->firstOrFail();

        $gatewayType = $gatewayType ?: Session::get($invitation->id . 'gateway_type');
        $paymentDriver = $invitation->account->paymentDriver($invitation, $gatewayType);

        if ($error = Input::get('error_description') ?: Input::get('error')) {
            return $this->error($paymentDriver, $error);
        }

        try {
            $paymentDriver->completeOffsitePurchase(Input::all());
            Session::flash('message', trans('texts.applied_payment'));
            return redirect()->to('view/' . $invitation->invitation_key);
        } catch (Exception $exception) {
            return $this->error($paymentDriver, $exception);
        }
    }

    private function error($paymentDriver, $exception, $showPayment)
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
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

}
