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

/*
'id' => 'pay_qw7rslcvacvubcvn6o5v7jp3ie',
  'requested_on' => '2023-07-21T05:49:29.0799437Z',
  'source' => 
  array (
    'id' => 'src_epivptv65yxungkhyqt6nayiai',
    'type' => 'card',
    'phone' => 
    array (
    ),
    'expiry_month' => 10,
    'expiry_year' => 2025,
    'scheme' => 'Visa',
    'last4' => '4242',
    'fingerprint' => 'BD864B08D0B098DD83052A038FD2BA967DF2D48E375AAEEF54E37BC36B385E9A',
    'bin' => '424242',
    'card_type' => 'CREDIT',
    'card_category' => 'CONSUMER',
    'issuer_country' => 'GB',
    'product_id' => 'F',
    'product_type' => 'Visa Classic',
    'avs_check' => 'G',
    'cvv_check' => 'Y',
    'payment_account_reference' => 'V001726431013874807',
  ),
  'expires_on' => '2023-08-20T05:50:08.7570835Z',
  'items' => 
  array (
  ),
  'amount' => 44520,
  'currency' => 'USD',
  'payment_type' => 'Regular',
  'reference' => '0024',
  'status' => 'Captured',
  'approved' => true,
  '3ds' => 
  array (
    'downgraded' => false,
    'enrolled' => 'Y',
    'authentication_response' => 'Y',
    'cryptogram' => 'AAABAVIREQAAAAAAAAAAAAAAAAA=',
    'xid' => 'e1331818-b517-439e-b186-e22bf4efbf4b',
    'version' => '2.2.0',
    'exemption' => 'none',
    'challenged' => true,
    'exemption_applied' => 'none',
  ),
  'balances' => 
  array (
    'total_authorized' => 44520,
    'total_voided' => 0,
    'available_to_void' => 0,
    'total_captured' => 44520,
    'available_to_capture' => 0,
    'total_refunded' => 0,
    'available_to_refund' => 44520,
  ),
  'risk' => 
  array (
    'flagged' => false,
    'score' => 0.0,
  ),
  'customer' => 
  array (
    'id' => 'cus_aarus35jqd5uddkcxqfd5gwiii',
    'email' => 'user@example.com',
    'name' => 'GBP',
  ),
  'metadata' => 
  array (
    'udf1' => 'Invoice Ninja',
    'udf2' => 'JUdUiwMNTV1qfSstvC0ZvUJSQVJ65DDC',
  ),
  'processing' => 
  array (
    'acquirer_transaction_id' => '767665093700479870728',
    'retrieval_reference_number' => '787770870837',
    'merchant_category_code' => '5815',
    'scheme_merchant_id' => '55500',
    'aft' => false,
    'cko_network_token_available' => false,
  ),
  'eci' => '05',
  'scheme_id' => '420920321590206',
  'actions' => 
  array (
    0 => 
    array (
      'id' => 'act_c3suhqtbmpjejltr6krvuknikm',
      'type' => 'Capture',
      'response_code' => '10000',
      'response_summary' => 'Approved',
    ),
    1 => 
    array (
      'id' => 'act_q4tjzvgsr2yu3cfzgrfn342wei',
      'type' => 'Authorization',
      'response_code' => '10000',
      'response_summary' => 'Approved',
    ),
  ),
  '_links' => 
  array (
    'self' => 
    array (
      'href' => 'https://api.sandbox.checkout.com/payments/pay_qw7rslcvacvubcvn6o5v7jp3ie',
    ),
    'actions' => 
    array (
      'href' => 'https://api.sandbox.checkout.com/payments/pay_qw7rslcvacvubcvn6o5v7jp3ie/actions',
    ),
    'refund' => 
    array (
      'href' => 'https://api.sandbox.checkout.com/payments/pay_qw7rslcvacvubcvn6o5v7jp3ie/refunds',
    ),
  ),
)  

*/

    /**
     * Creates an authentication workflow for 3DS
     * and also a registration mechanism for payments that have been approved.
     *
     * @return void
     */
    public function createAuthenticationWorkflow()
    {

        $signature = new WebhookSignature();
        $signature->key = $this->checkout->company_gateway->company->company_key;
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

    /**
     * Lists all possible events in checkout and a brief description
     *
     * @return void
     */
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

    /**
     * Lists the workflows in Checkout
     *
     * @return void
     */
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