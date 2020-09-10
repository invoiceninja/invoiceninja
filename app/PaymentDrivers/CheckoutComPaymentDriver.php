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

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\BaseDriver;
use App\PaymentDrivers\CheckoutCom\Utilities;
use App\Utils\Ninja;
use App\Utils\Traits\SystemLogTrait;
use Checkout\CheckoutApi;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Models\Payments\IdSource;
use Checkout\Models\Payments\Payment as CheckoutPayment;
use Checkout\Models\Payments\TokenSource;

class CheckoutComPaymentDriver extends BaseDriver
{
    use SystemLogTrait, Utilities;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    public $invitation;

    /* Gateway capabilities */
    public $refundable = true;

    /* Token billing */
    public $token_billing = true;

    /* Authorise payment methods */
    public $can_authorise_credit_card = true;

    /** Instance of \Checkout\CheckoutApi */
    public $gateway;

    public static $methods = [
        GatewayType::CREDIT_CARD => '',
    ];

    /**
     * Returns the default gateway type.
     */
    public function gatewayTypes()
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }

    /** 
     * Since with Checkout.com we handle only credit cards, this method should be empty.
     * @param $string payment_method string
     */
    public function setPaymentMethod($payment_method = null)
    {
        return $this;
    }

    /**
     * Initialize the checkout payment driver
     * @return $this
     */
    public function init() 
    {
        $config = [
            'secret' =>  $this->company_gateway->getConfigField('secretApiKey'),
            'public' =>  $this->company_gateway->getConfigField('publicApiKey'),
            'sandbox' => $this->company_gateway->getConfigField('testMode'),
        ];

        $this->gateway = new CheckoutApi($config['secret'], $config['sandbox'], $config['public']);

        return $this;
    }

    /**
     * Process different view depending on payment type
     * @param  int      $gateway_type_id    The gateway type
     * @return string                       The view string
     */
    public function viewForType($gateway_type_id)
    {
        if ($gateway_type_id == GatewayType::CREDIT_CARD) {
            return 'gateways.checkout.credit_card';
        }

        if ($gateway_type_id == GatewayType::TOKEN) {
            return 'gateways.checkout.credit_card';
        }
    }

    /**
     * Authorization view
     * 
     * @param  array $data  Payment data array
     * @return view         Authorization View
     */
    public function authorizeView($data)
    {
        return render('gateways.checkout.authorize');
    }

    /**
     * Payment View
     * 
     * @param  array  $data Payment data array
     * @return view         The payment view
     */
    public function processPaymentView(array $data)
    {
        $data['gateway'] = $this;
        $data['company_gateway'] = $this->company_gateway;
        $data['client'] = $this->client;
        $data['currency'] = $this->client->getCurrencyCode();
        $data['value'] = $this->convertToCheckoutAmount($data['amount_with_fee'], $this->client->getCurrencyCode());
        $data['raw_value'] = $data['amount_with_fee'];
        $data['customer_email'] = $this->client->present()->email;

        return render($this->viewForType($data['payment_method_id']), $data);
    }

    /**
     * Process the payment response
     * 
     * @param  Request $request The payment request
     * @return view             The payment response view
     */
    public function processPaymentResponse($request)
    {
        $this->init();

        $state = [
            'server_response' => json_decode($request->gateway_response),
            'value' => $request->value,
            'raw_value' => $request->raw_value,
            'currency' => $request->currency,
            'payment_hash' =>$request->payment_hash,
            'reference' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        if ($request->has('token') && ! is_null($request->token)) {
            $method = new IdSource($state['token']);
            $payment = new CheckoutPayment($method, $state['currency']);
            $payment->amount = $state['value'];
            $payment->reference = $state['reference'];
        } else {
            $method = new TokenSource($state['server_response']->cardToken);
            $payment = new CheckoutPayment($method, $state['currency']);
            $payment->amount = $state['value'];
            $payment->reference = $state['reference'];

            if ($this->client->currency()->code === 'EUR') {
                $payment->{'3ds'} = ['enabled' => true];
            }
        }

        try {
            $response = $this->gateway->payments()->request($payment);
            $state['payment_response'] = $response;

            if ($response->status === 'Authorized') {
                return $this->processSuccessfulPayment($state);
            }

            if ($response->status === 'Pending') {
                return $this->processPendingPayment($state);
            }

            if ($response->status === 'Declined') {
                return $this->processUnsuccessfulPayment($state);
            }
        } catch (CheckoutHttpException $e) {
            return $this->processInternallyFailedPayment($e, $state);
        }
    }

    /**
     * Process a successful payment response
     * 
     * @param  array $state  The state array
     * @return view          The response
     */
    public function processSuccessfulPayment($state)
    {
        $state['charge_id'] = $state['payment_response']->id;

        if (isset($state['store_card']) && $state['store_card']) {
            $this->saveCard($state);
        }

        $data = [
            'payment_method' => $state['charge_id'],
            'payment_type' => PaymentType::parseCardType($state['payment_response']->source['scheme']),
            'amount' => $state['raw_value'],
        ];

        $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);
        $payment_hash = PaymentHash::whereRaw('BINARY `hash`= ?', [$state['payment_hash']])->firstOrFail();
        $payment_hash->payment_id = $payment->id;
        $payment_hash->save();
        
        $this->attachInvoices($payment, $payment_hash);
        $payment->service()->updateInvoicePayment($payment_hash);

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        $logger_message = [
            'server_response' => $state['payment_response'],
            'data' => $data,
        ];

        SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_CHECKOUT, $this->client);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    public function processPendingPayment($state)
    {
        $state['charge_id'] = $state['payment_response']->id;

        if (isset($state['store_card']) && $state['store_card']) {
            $this->saveCard($state);
        }

        $data = [
            'payment_method' => $state['charge_id'],
            'payment_type' => PaymentType::parseCardType($state['payment_response']->source['scheme']),
            'amount' => $state['raw_value'],
        ];

        $payment = $this->createPayment($data, Payment::STATUS_PENDING);

        $this->attachInvoices($payment, $state['hashed_ids']);

        $payment->service()->updateInvoicePayment();

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        $logger_message = [
            'server_response' => $state['payment_response'],
            'data' => $data,
        ];

        SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_CHECKOUT, $this->client);

        try {
            return redirect($state['payment_response']->_links['redirect']['href']);
        } catch (\Exception $e) {
            SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_CHECKOUT, $this->client);

            throw new \Exception('Failed to process the payment.', 1);
        }
    }

    public function processUnsuccessfulPayment($state)
    {
        PaymentFailureMailer::dispatch($this->client, $state['payment_response']->response_summary, $this->client->company, $state['payment_response']->amount);

        $message = [
            'server_response' => $state['server_response'],
            'data' => $state,
        ];

        SystemLogger::dispatch($message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_CHECKOUT, $this->client);

        // throw new \Exception('Failed to process the payment: ' . $state['payment_response']->response_summary, 1);

        return render('gateways.unsuccessful', [
            'code' => $state['payment_response']->response_code,
        ]);
    }

    public function processInternallyFailedPayment($e, $state)
    {
        $message = json_decode($e->getBody());

        PaymentFailureMailer::dispatch($this->client, $message->error_type, $this->client->company, $state['value']);

        $message = [
            'server_response' => $state['server_response'],
            'data' => $message,
        ];

        SystemLogger::dispatch($message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_CHECKOUT, $this->client);

        throw new \Exception('Failed to process the payment.', 1);
    }

    public function createPayment($data, $status = Payment::STATUS_COMPLETED): Payment
    {
        $payment = parent::createPayment($data, $status);

        $client_contact = $this->getContact();
        $client_contact_id = $client_contact ? $client_contact->id : null;

        $payment->amount = $data['amount'];
        $payment->type_id = $data['payment_type'];
        $payment->transaction_reference = $data['payment_method'];
        $payment->client_contact_id = $client_contact_id;
        $payment->save();

        return $payment;
    }

    public function saveCard($state)
    {
        $company_gateway_token = new ClientGatewayToken();
        $company_gateway_token->company_id = $this->client->company->id;
        $company_gateway_token->client_id = $this->client->id;
        $company_gateway_token->token = $state['payment_response']->source['id'];
        $company_gateway_token->company_gateway_id = $this->company_gateway->id;
        $company_gateway_token->gateway_type_id = $state['payment_method_id'];
        $company_gateway_token->meta = $state['payment_response']->source;
        $company_gateway_token->save();

        if ($this->client->gateway_tokens->count() == 1) {
            $this->client->gateway_tokens()->update(['is_default' => 0]);

            $company_gateway_token->is_default = 1;
            $company_gateway_token->save();
        }
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        $checkout_payment = new \Checkout\Models\Payments\Refund($payment->transaction_reference);

        try {
            $refund = $this->gateway->payments()->refund($checkout_payment);
            $checkout_payment = $this->gateway->payments()->details($refund->id);

            $response = ['refund_response' => $refund, 'checkout_payment_fetch' => $checkout_payment];

            return [
                'transaction_reference' => $refund->action_id,
                'transaction_response' => json_encode($response),
                'success' => $checkout_payment->status == 'Refunded',
                'description' => $checkout_payment->status,
                'code' => $checkout_payment->http_code,
            ];
        } catch (CheckoutHttpException $e) {
            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }
}
