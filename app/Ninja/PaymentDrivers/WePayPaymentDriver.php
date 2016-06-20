<?php namespace App\Ninja\PaymentDrivers;

class WePayPaymentDriver extends BasePaymentDriver
{
    protected function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_CREDIT_CARD,
            GATEWAY_TYPE_BANK_TRANSFER,
            GATEWAY_TYPE_TOKEN
        ];
    }

    public function startPurchase($input, $sourceId)
    {
        $data = parent::startPurchase($input, $sourceId);

        if ($this->isGatewayType(GATEWAY_TYPE_BANK_TRANSFER)) {
            if ( ! $sourceId) {
                throw new Exception();
            }
        }

        return $data;
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

        if ($transactionId = Session::get($invitation->id . 'payment_ref')) {
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

    public function removePaymentMethod($paymentMethod)
    {
        $wepay = Utils::setupWePay($this->accountGateway);
        $wepay->request('/credit_card/delete', [
            'client_id' => WEPAY_CLIENT_ID,
            'client_secret' => WEPAY_CLIENT_SECRET,
            'credit_card_id' => intval($paymentMethod->source_reference),
        ]);

        if ($response->isSuccessful()) {
            return parent::removePaymentMethod($paymentMethod);
        } else {
            throw new Exception($response->getMessage());
        }
    }

    protected function refundDetails($payment, $amount)
    {
        $data = parent::refundDetails($parent);

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
