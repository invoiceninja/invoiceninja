<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;

/**
 * Class CustomPaymentDriver.
 */
class CustomPaymentDriver extends BaseDriver
{
    public $token_billing = false;

    public $can_authorise_credit_card = false;

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [
            GatewayType::CREDIT_CARD,
        ];

        return $types;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;

        return $this;
    }

    public function processPaymentView($data)
    {
        $data['title'] = $this->company_gateway->getConfigField('name');
        $data['instructions'] = $this->company_gateway->getConfigField('text');
        
        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, $data);
        $this->payment_hash->save();
        
        $data['gateway'] = $this;

        return render('gateways.custom.payment', $data);
    }

    public function processPaymentResponse($request)
    {
        $data = [
            'payment_method' => GatewayType::CREDIT_CARD,
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'amount' => $this->payment_hash->data->amount_with_fee,
            'transaction_reference' => \Illuminate\Support\Str::uuid(),
        ];

        $payment = $this->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->client,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    /**
     * Detach payment method from custom payment driver.
     *
     * @param ClientGatewayToken $token
     * @return void
     */
    public function detach(ClientGatewayToken $token)
    {
        // Driver doesn't support this feature.
    }
}
