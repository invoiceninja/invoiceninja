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

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;

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
    public function gatewayTypes() :array
    {
        $types = [
            GatewayType::CREDIT_CARD,
        ];

        return $types;
    }

    public function authorize($payment_method)
    {
    }

    public function purchase($amount, $return_client_response = false)
    {
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
    }

    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;

        return $this;
    }

    public function processPaymentView($data)
    {
        return render('gateways.custom.landing_page', $data);
    }

    public function processPaymentResponse($request)
    {
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
