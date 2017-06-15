<?php

namespace App\Ninja\PaymentDrivers;

use App\Models\Payment;
use App\Models\PaymentMethod;
use Exception;
use Session;
use Utils;

class WePayPaymentDriver extends BasePaymentDriver
{
    public $canRefundPayments = true;

    public function gatewayTypes()
    {
        $types = [
            GATEWAY_TYPE_CREDIT_CARD,
            GATEWAY_TYPE_TOKEN,
        ];

        if ($this->accountGateway && $this->accountGateway->getAchEnabled()) {
            $types[] = GATEWAY_TYPE_BANK_TRANSFER;
        }

        return $types;
    }

    public function tokenize()
    {
        return true;
    }

    protected function checkCustomerExists($customer)
    {
        return true;
    }

    public function rules()
    {
        $rules = parent::rules();

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            $rules = array_merge($rules, [
                'authorize_ach' => 'required',
                'tos_agree' => 'required',
            ]);
        }

        return $rules;
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        if ($transactionId = Session::get($this->invitation->id . 'payment_ref')) {
            $data['transaction_id'] = $transactionId;
        }

        $data['feePayer'] = env('WEPAY_FEE_PAYER');
        $data['callbackUri'] = $this->accountGateway->getWebhookUrl();

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER, $paymentMethod)) {
            $data['paymentMethodType'] = 'payment_bank';
            $data['applicationFee'] = (env('WEPAY_APP_FEE_ACH_MULTIPLIER') * $data['amount']) + env('WEPAY_APP_FEE_FIXED');
        } else {
            $data['applicationFee'] = (env('WEPAY_APP_FEE_CC_MULTIPLIER') * $data['amount']) + env('WEPAY_APP_FEE_FIXED');
        }

        $data['transaction_rbits'] = $this->invoice()->present()->rBits;

        return $data;
    }

    public function createToken()
    {
        $wepay = Utils::setupWePay($this->accountGateway);
        $token = intval($this->input['sourceToken']);

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            // Persist bank details
            $this->tokenResponse = $wepay->request('/payment_bank/persist', [
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'payment_bank_id' => $token,
            ]);
        } else {
            // Authorize credit card
            $tokenResponse = $wepay->request('credit_card/authorize', [
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => $token,
            ]);

            // Update the callback uri and get the card details
            $tokenResponse = $wepay->request('credit_card/modify', [
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => $token,
                'auto_update' => WEPAY_AUTO_UPDATE,
                'callback_uri' => $this->accountGateway->getWebhookUrl(),
            ]);

            $this->tokenResponse = $wepay->request('credit_card', [
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => $token,
            ]);
        }

        return parent::createToken();
    }

    /*
    public function creatingCustomer($customer)
    {
        if ($gatewayResponse instanceof \Omnipay\WePay\Message\CustomCheckoutResponse) {
            $wepay = \Utils::setupWePay($accountGateway);
            $paymentMethodType = $gatewayResponse->getData()['payment_method']['type'];

            $gatewayResponse = $wepay->request($paymentMethodType, array(
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                $paymentMethodType.'_id' => $gatewayResponse->getData()['payment_method'][$paymentMethodType]['id'],
            ));
        }
    }
    */

    protected function creatingPaymentMethod($paymentMethod)
    {
        $source = $this->tokenResponse;

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            $paymentMethod->payment_type_id = PAYMENT_TYPE_ACH;
            $paymentMethod->last4 = $source->account_last_four;
            $paymentMethod->bank_name = $source->bank_name;
            $paymentMethod->source_reference = $source->payment_bank_id;

            switch ($source->state) {
                case 'new':
                case 'pending':
                    $paymentMethod->status = 'new';
                    break;
                case 'authorized':
                    $paymentMethod->status = 'verified';
                    break;
            }
        } else {
            $paymentMethod->last4 = $source->last_four;
            $paymentMethod->payment_type_id = $this->parseCardType($source->credit_card_name);
            $paymentMethod->expiration = $source->expiration_year . '-' . $source->expiration_month . '-01';
            $paymentMethod->source_reference = $source->credit_card_id;
        }

        return $paymentMethod;
    }

    public function removePaymentMethod($paymentMethod)
    {
        parent::removePaymentMethod($paymentMethod);

        $wepay = Utils::setupWePay($this->accountGateway);
        $response = $wepay->request('/credit_card/delete', [
            'client_id' => WEPAY_CLIENT_ID,
            'client_secret' => WEPAY_CLIENT_SECRET,
            'credit_card_id' => intval($paymentMethod->source_reference),
        ]);

        if ($response->state == 'deleted') {
            return true;
        } else {
            throw new Exception(trans('texts.failed_remove_payment_method'));
        }
    }

    protected function refundDetails($payment, $amount)
    {
        $data = parent::refundDetails($payment, $amount);

        $data['refund_reason'] = 'Refund issued by merchant.';

        // WePay issues a full refund when no amount is set. If an amount is set, it will try
        // to issue a partial refund without refunding any fees. However, the Stripe driver
        // (but not the API) requires the amount parameter to be set no matter what.
        if ($data['amount'] == $payment->getCompletedAmount()) {
            unset($data['amount']);
        }

        return $data;
    }

    protected function attemptVoidPayment($response, $payment, $amount)
    {
        if (! parent::attemptVoidPayment($response, $payment, $amount)) {
            return false;
        }

        return $response->getCode() == 4004;
    }

    public function handleWebHook($input)
    {
        $accountGateway = $this->accountGateway;
        $accountId = $accountGateway->account_id;

        foreach (array_keys($input) as $key) {
            if ('_id' == substr($key, -3)) {
                $objectType = substr($key, 0, -3);
                $objectId = $input[$key];
                break;
            }
        }

        if (! isset($objectType)) {
            throw new Exception('Could not find object id parameter');
        }

        if ($objectType == 'credit_card') {
            $paymentMethod = PaymentMethod::scope(false, $accountId)->where('source_reference', '=', $objectId)->first();

            if (! $paymentMethod) {
                throw new Exception('Unknown payment method');
            }

            $wepay = Utils::setupWePay($accountGateway);
            $source = $wepay->request('credit_card', [
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => intval($objectId),
            ]);

            if ($source->state == 'deleted') {
                $paymentMethod->delete();
            } else {
                //$this->paymentService->convertPaymentMethodFromWePay($source, null, $paymentMethod)->save();
            }

            return 'Processed successfully';
        } elseif ($objectType == 'account') {
            $config = $accountGateway->getConfig();
            if ($config->accountId != $objectId) {
                throw new Exception('Unknown account');
            }

            $wepay = Utils::setupWePay($accountGateway);
            $wepayAccount = $wepay->request('account', [
                'account_id' => intval($objectId),
            ]);

            if ($wepayAccount->state == 'deleted') {
                $accountGateway->delete();
            } else {
                $config->state = $wepayAccount->state;
                $accountGateway->setConfig($config);
                $accountGateway->save();
            }

            return ['message' => 'Processed successfully'];
        } elseif ($objectType == 'checkout') {
            $payment = Payment::scope(false, $accountId)->where('transaction_reference', '=', $objectId)->first();

            if (! $payment) {
                throw new Exception('Unknown payment');
            }

            $wepay = Utils::setupWePay($accountGateway);
            $checkout = $wepay->request('checkout', [
                'checkout_id' => intval($objectId),
            ]);

            if ($checkout->state == 'refunded') {
                $payment->recordRefund();
            } elseif (! empty($checkout->refund) && ! empty($checkout->refund->amount_refunded) && ($checkout->refund->amount_refunded - $payment->refunded) > 0) {
                $payment->recordRefund($checkout->refund->amount_refunded - $payment->refunded);
            }

            if ($checkout->state == 'captured') {
                $payment->markComplete();
            } elseif ($checkout->state == 'cancelled') {
                $payment->markCancelled();
            } elseif ($checkout->state == 'failed') {
                $payment->markFailed();
            }

            return 'Processed successfully';
        } else {
            return 'Ignoring event';
        }
    }
}
