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

use App\Utils\Traits\MakesHash;
use Checkout\CheckoutApiException;
use Checkout\CheckoutAuthorizationException;
use Checkout\Workflows\CreateWorkflowRequest;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\Workflows\Actions\WebhookSignature;
use Checkout\Workflows\Actions\WebhookWorkflowActionRequest;
use Checkout\Workflows\Conditions\EventWorkflowConditionRequest;
use Checkout\Workflows\Conditions\EntityWorkflowConditionRequest;
use Checkout\Workflows\Conditions\ProcessingChannelWorkflowConditionRequest;

class Webhook
{
    /**
     * @var CheckoutComPaymentDriver
     */
    public $checkout;

    private string $authentication_webhook_name = 'Invoice_Ninja_3DS_Workflow';

    public function __construct(CheckoutComPaymentDriver $checkout)
    {
        $this->checkout = $checkout;

        $this->checkout->init();
    }

    public function checkStatus()
    {
        // $this->checkout->company_gateway->webhookUrl()
    }

    public function createAuthenticationWorkflow()
    {

        $signature = new WebhookSignature();
        $signature->key = "1234567890";
        $signature->method = "HMACSHA256";

        $actionRequest = new WebhookWorkflowActionRequest();
        $actionRequest->url = $this->checkout->company_gateway->webhookUrl();
        $actionRequest->signature = $signature;

        $eventWorkflowConditionRequest = new EventWorkflowConditionRequest();
        $eventWorkflowConditionRequest->events = [
            "gateway" => ["payment_approved"],
            "issuing" => ["authorization_approved","authorization_declined"],
        ];

        $request = new CreateWorkflowRequest();
        $request->actions = [$actionRequest];
        $request->conditions = [$eventWorkflowConditionRequest];
        $request->name = $this->authentication_webhook_name;
        $request->active = true;

        try {
            $response = $this->checkout->gateway->getWorkflowsClient()->createWorkflow($request);
            
            nlog($response);

        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
            nlog($error_details);
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization
        }



    }

    public function getEventTypes()
    {
        try {
            $response = $this->checkout->gateway->getWorkflowsClient()->getEventTypes();

            nlog($response);

        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;
            nlog($error_details);

            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization
        }

    }

    public function getWorkFlows()
    {

        try {
            $response = $this->checkout->gateway->getWorkflowsClient()->getWorkflows();

            nlog($response);

        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization
        }



    }

}