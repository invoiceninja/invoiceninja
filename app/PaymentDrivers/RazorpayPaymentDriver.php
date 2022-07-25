<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Razorpay\Hosted;
use App\Utils\Traits\MakesHash;

class RazorpayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    public \Razorpay\Api\Api $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::HOSTED_PAGE => Hosted::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_RAZORPAY;

    public function init(): self
    {
        $this->gateway = new \Razorpay\Api\Api(
            $this->company_gateway->getConfigField('apiKey'),
            $this->company_gateway->getConfigField('apiSecret'),
        );

        return $this;
    }

    public function gatewayTypes(): array
    {
        return [
            GatewayType::HOSTED_PAGE,
        ];
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }

    /**
     * Convert the amount to the format that Razorpay supports.
     *
     * @param mixed|float $amount
     * @return int
     */
    public function convertToRazorpayAmount($amount): int
    {
        return \number_format((float) $amount * 100, 0, '.', '');
    }

    public function processWebhookRequest(): void
    {
        //
    }
}
