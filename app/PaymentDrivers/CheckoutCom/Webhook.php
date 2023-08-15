<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\CheckoutCom;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use App\Utils\Traits\MakesHash;
use Checkout\CheckoutApiException;
use Checkout\CheckoutArgumentException;
use Checkout\CheckoutAuthorizationException;
use Checkout\Payments\Four\Request\PaymentRequest;
use Checkout\Payments\Four\Request\Source\RequestTokenSource;
use Checkout\Payments\PaymentRequest as PaymentsPaymentRequest;
use Checkout\Payments\Source\RequestTokenSource as SourceRequestTokenSource;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Webhook
{

    public function __construct(public CheckoutComPaymentDriver $checkout)
    {
        $this->checkout = $checkout;
    }

    /**
     * Lists all possible events in checkout and a brief description
     *
     * @return void
     */
    public function getEventTypes()
    {
        try {
            $response = $this->checkout->gateway->getWorkflowsClient()->getEventTypes();

            return $response;

        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;
            nlog($error_details);

            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization
        }

    }

    /**
     * Lists the workflows in Checkout
     */
    public function getWorkFlows()
    {

        try {
            
            $response = $this->checkout->gateway->getWorkflowsClient()->getWorkflows();

            return $response;

        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization
        }

    }

}