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

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Utils\HtmlEngine;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Models\ClientGatewayToken;
use Illuminate\Support\Facades\Http;
use App\PaymentDrivers\CBAPowerBoard\CreditCard;

/**
 * Class CBAPowerBoardPaymentDriver.
 */
class CBAPowerBoardPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $token_billing = true;

    public $can_authorise_credit_card = false;

    public $refundable = true;

    public string $api_endpoint = 'https://api.powerboard.commbank.com.au';

    public string $widget_endpoint = 'https://widget.powerboard.commbank.com.au/sdk/latest/widget.umd.min.js';

    public string $environment = 'production_cba';

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];
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

    public function init(): self
    {
        if($this->company_gateway->getConfigField('testMode')) {
            $this->widget_endpoint = 'https://widget.preproduction.powerboard.commbank.com.au/sdk/latest/widget.umd.min.js';
            $this->api_endpoint = 'https://api.preproduction.powerboard.commbank.com.au';     
            $this->environment = 'preproduction_cba';   
        }

        return $this;
    }

    public function setPaymentMethod($payment_method_id)
    {

        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

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
        $this->init();

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
     * @return bool
     */
    public function detach(ClientGatewayToken $token): bool
    {
        // Driver doesn't support this feature.
        return true;
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
    }

    public function importCustomers()
    {
    }

    public function auth(): bool
    {
        $this->init();


        return true;
        // try {
        //     $this->verifyConnect();
        //     return true;
        // } catch(\Exception $e) {

        // }

        // return false;

    }

    public function gatewayRequest(string $uri, string $verb, array $payload, array $headers = [])
    {
        $r = Http::withHeaders($this->getHeaders($headers))
                   ->{$verb}($this->api_endpoint.$uri, $payload);
    }

    public function getHeaders(array $headers = []): array
    {
        return array_merge([
            'x-user-secret-key' => $this->company_gateway->getConfigField('secretKey'),
            'Content-Type' => 'application/json',
        ],
        $headers);
    }

}
