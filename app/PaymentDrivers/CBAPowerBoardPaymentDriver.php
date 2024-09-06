<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;

/**
 * Class CBAPowerBoardPaymentDriver.
 */
class CBAPowerBoardPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $token_billing = true;

    public $can_authorise_credit_card = false;

    public $refundable = true;

    protected $api_endpoint = '';

    protected $widget_endpoint = '';
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

    public function init()
    {
// $this->company_gateway->getConfigField('account_id')

        return $this;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;

        return $this;
    }

    /**
     * View for displaying custom content of the driver.
     *
     * @param array $data
     * @return mixed
     */
    public function processPaymentView($data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Processing method for payment. Should never be reached with this driver.
     *
     * @return mixed
     */
    public function processPaymentResponse($request)
    {       
        return $this->payment_method->paymentResponse($request);
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

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {

    }

    public function processWebhookRequest($request)
    {
    }

    public function getClientRequiredFields(): array
    {
        return [];
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return (new Charge($this))->tokenBilling($cgt, $payment_hash);
    }

    public function importCustomers()
    {
    }

    public function auth(): bool
    {
        $this->init();

        // try {
        //     $this->verifyConnect();
        //     return true;
        // } catch(\Exception $e) {

        // }

        // return false;

    }
}
