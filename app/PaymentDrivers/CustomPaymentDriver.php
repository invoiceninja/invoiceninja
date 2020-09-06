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
use App\Models\Invoice;
use App\Models\Payment;
use App\PaymentDrivers\Authorize\AuthorizeCreditCard;
use App\PaymentDrivers\Authorize\AuthorizePaymentMethod;
use App\PaymentDrivers\Authorize\ChargePaymentProfile;
use App\PaymentDrivers\Authorize\RefundTransaction;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\GetMerchantDetailsRequest;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\controller\GetMerchantDetailsController;

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
}
