<?php namespace App\Ninja\PaymentDrivers;

use Session;
use Utils;
use Exception;

class WePayPaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        $types =  [
            GATEWAY_TYPE_CREDIT_CARD,
            GATEWAY_TYPE_TOKEN
        ];

        if ($this->accountGateway && $this->accountGateway->getAchEnabled()) {
            $types[] = GATEWAY_TYPE_BANK_TRANSFER;
        }

        return $types;
    }

    /*
    public function startPurchase($input = false, $sourceId = false)
    {
        $data = parent::startPurchase($input, $sourceId);

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            if ( ! $sourceId) {
                throw new Exception();
            }
        }

        return $data;
    }
    */

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

        $data['applicationFee'] = $this->calculateApplicationFee($data['amount']);
        $data['feePayer'] = WEPAY_FEE_PAYER;
        $data['callbackUri'] = $this->accountGateway->getWebhookUrl();

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            $data['paymentMethodType'] = 'payment_bank';
        }

        return $data;
    }

    public function createToken()
    {
        $wepay = Utils::setupWePay($this->accountGateway);
        $token = intval($this->input['sourceToken']);

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            // Persist bank details
            $this->tokenResponse = $wepay->request('/payment_bank/persist', array(
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'payment_bank_id' => $token,
            ));
        } else {
            // Authorize credit card
            $tokenResponse = $wepay->request('credit_card/authorize', array(
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => $token,
            ));

            // Update the callback uri and get the card details
            $tokenResponse = $wepay->request('credit_card/modify', array(
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => $token,
                'auto_update' => WEPAY_AUTO_UPDATE,
                'callback_uri' => $this->accountGateway->getWebhookUrl(),
            ));

            $this->tokenResponse = $wepay->request('credit_card', array(
                'client_id' => WEPAY_CLIENT_ID,
                'client_secret' => WEPAY_CLIENT_SECRET,
                'credit_card_id' => $token,
            ));
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

            switch($source->state) {
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
        $wepay = Utils::setupWePay($this->accountGateway);
        $response = $wepay->request('/credit_card/delete', [
            'client_id' => WEPAY_CLIENT_ID,
            'client_secret' => WEPAY_CLIENT_SECRET,
            'credit_card_id' => intval($paymentMethod->source_reference),
        ]);

        if ($response->state == 'deleted') {
            return parent::removePaymentMethod($paymentMethod);
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
        if ( ! parent::attemptVoidPayment($response, $payment, $amount)) {
            return false;
        }

        return $response->getCode() == 4004;
    }

    private function calculateApplicationFee($amount)
    {
        $fee = WEPAY_APP_FEE_MULTIPLIER * $amount + WEPAY_APP_FEE_FIXED;

        return floor(min($fee, $amount * 0.2));// Maximum fee is 20% of the amount.
    }

}
