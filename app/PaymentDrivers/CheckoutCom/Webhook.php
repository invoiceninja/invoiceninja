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

namespace App\PaymentDrivers\CheckoutCom;

use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\CheckoutApiException;
use Checkout\CheckoutAuthorizationException;

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

            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null; //@phpstan-ignore-line
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
