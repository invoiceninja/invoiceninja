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
            return $this->error($paymentDriver, $exception);
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

    private function error($paymentDriver, $exception)
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

        return redirect()->to('view/' . $paymentDriver->invitation->invitation_key);
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

        switch($gatewayId) {
            case GATEWAY_STRIPE:
                return $this->handleStripeWebhook($accountGateway);
            case GATEWAY_WEPAY:
                return $this->handleWePayWebhook($accountGateway);
            default:
                return response()->json([
                    'message' => 'Unsupported gateway',
                ], 404);
        }
    }

    protected function handleWePayWebhook($accountGateway) {
        $data = Input::all();
        $accountId = $accountGateway->account_id;

        foreach (array_keys($data) as $key) {
            if ('_id' == substr($key, -3)) {
                $objectType = substr($key, 0, -3);
                $objectId = $data[$key];
                break;
            }
        }

        if (!isset($objectType)) {
            return response()->json([
                'message' => 'Could not find object id parameter',
            ], 400);
        }

        if ($objectType == 'credit_card') {
            $paymentMethod = PaymentMethod::scope(false, $accountId)->where('source_reference', '=', $objectId)->first();

            if (!$paymentMethod) {
                return array('message' => 'Unknown payment method');
            }

            $wepay = \Utils::setupWePay($accountGateway);
            $source = $wepay->request('credit_card', array(
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => intval($objectId),
            ));

            if ($source->state == 'deleted') {
                $paymentMethod->delete();
            } else {
                $this->paymentService->convertPaymentMethodFromWePay($source, null, $paymentMethod)->save();
            }

            return array('message' => 'Processed successfully');
        } elseif ($objectType == 'account') {
            $config = $accountGateway->getConfig();
            if ($config->accountId != $objectId) {
                return array('message' => 'Unknown account');
            }

            $wepay = \Utils::setupWePay($accountGateway);
            $wepayAccount = $wepay->request('account', array(
                'account_id' => intval($objectId),
            ));

            if ($wepayAccount->state == 'deleted') {
                $accountGateway->delete();
            } else {
                $config->state = $wepayAccount->state;
                $accountGateway->setConfig($config);
                $accountGateway->save();
            }

            return array('message' => 'Processed successfully');
        } elseif ($objectType == 'checkout') {
            $payment = Payment::scope(false, $accountId)->where('transaction_reference', '=', $objectId)->first();

            if (!$payment) {
                return array('message' => 'Unknown payment');
            }

            $wepay = \Utils::setupWePay($accountGateway);
            $checkout = $wepay->request('checkout', array(
                'checkout_id' => intval($objectId),
            ));

            if ($checkout->state == 'refunded') {
                $payment->recordRefund();
            } elseif (!empty($checkout->refund) && !empty($checkout->refund->amount_refunded) && ($checkout->refund->amount_refunded - $payment->refunded) > 0) {
                $payment->recordRefund($checkout->refund->amount_refunded - $payment->refunded);
            }

            if ($checkout->state == 'captured') {
                $payment->markComplete();
            } elseif ($checkout->state == 'cancelled') {
                $payment->markCancelled();
            } elseif ($checkout->state == 'failed') {
                $payment->markFailed();
            }

            return array('message' => 'Processed successfully');
        } else {
            return array('message' => 'Ignoring event');
        }
    }

    protected function handleStripeWebhook($accountGateway) {
        $eventId = Input::get('id');
        $eventType= Input::get('type');
        $accountId = $accountGateway->account_id;

        if (!$eventId) {
            return response()->json(['message' => 'Missing event id'], 400);
        }

        if (!$eventType) {
            return response()->json(['message' => 'Missing event type'], 400);
        }

        $supportedEvents = array(
            'charge.failed',
            'charge.succeeded',
            'customer.source.updated',
            'customer.source.deleted',
        );

        if (!in_array($eventType, $supportedEvents)) {
            return array('message' => 'Ignoring event');
        }

        // Fetch the event directly from Stripe for security
        $eventDetails = $this->paymentService->makeStripeCall($accountGateway, 'GET', 'events/'.$eventId);

        if (is_string($eventDetails) || !$eventDetails) {
            return response()->json([
                'message' => $eventDetails ? $eventDetails : 'Could not get event details.',
            ], 500);
        }

        if ($eventType != $eventDetails['type']) {
            return response()->json(['message' => 'Event type mismatch'], 400);
        }

        if (!$eventDetails['pending_webhooks']) {
            return response()->json(['message' => 'This is not a pending event'], 400);
        }


        if ($eventType == 'charge.failed' || $eventType == 'charge.succeeded') {
            $charge = $eventDetails['data']['object'];
            $transactionRef = $charge['id'];

            $payment = Payment::scope(false, $accountId)->where('transaction_reference', '=', $transactionRef)->first();

            if (!$payment) {
                return array('message' => 'Unknown payment');
            }

            if ($eventType == 'charge.failed') {
                if (!$payment->isFailed()) {
                    $payment->markFailed($charge['failure_message']);
                    $this->userMailer->sendNotification($payment->user, $payment->invoice, 'payment_failed', $payment);
                }
            } elseif ($eventType == 'charge.succeeded') {
                $payment->markComplete();
            } elseif ($eventType == 'charge.refunded') {
                $payment->recordRefund($charge['amount_refunded'] / 100 - $payment->refunded);
            }
        } elseif($eventType == 'customer.source.updated' || $eventType == 'customer.source.deleted') {
            $source = $eventDetails['data']['object'];
            $sourceRef = $source['id'];

            $paymentMethod = PaymentMethod::scope(false, $accountId)->where('source_reference', '=', $sourceRef)->first();

            if (!$paymentMethod) {
                return array('message' => 'Unknown payment method');
            }

            if ($eventType == 'customer.source.deleted') {
                $paymentMethod->delete();
            } elseif ($eventType == 'customer.source.updated') {
                $this->paymentService->convertPaymentMethodFromStripe($source, null, $paymentMethod)->save();
            }
        }

        return array('message' => 'Processed successfully');
    }

}
