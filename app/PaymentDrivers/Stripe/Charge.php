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

namespace App\PaymentDrivers\Stripe;

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\StripeClient;

class Charge
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Create a charge against a payment method.
     * @param ClientGatewayToken $cgt
     * @param PaymentHash $payment_hash
     * @return bool success/failure
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        if ($cgt->gateway_type_id == GatewayType::BANK_TRANSFER) {
            return (new ACH($this->stripe))->tokenBilling($cgt, $payment_hash);
        }

        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $invoice = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->stripe->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->stripe->client->present()->name()}";
        }

        $this->stripe->init();

        $response = null;

        try {
            $data = [
                'amount' => $this->stripe->convertToStripeAmount($amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
                'currency' => $this->stripe->client->getCurrencyCode(),
                'payment_method' => $cgt->token,
                'customer' => $cgt->gateway_customer_reference,
                'confirm' => true,
                'description' => $description,
                'metadata' => [
                    'payment_hash' => $payment_hash->hash,
                    'gateway_type_id' => $cgt->gateway_type_id,
                ],
            ];

            if ($cgt->gateway_type_id == GatewayType::SEPA) {
                $data['payment_method_types'] = ['sepa_debit'];
            }

            $response = $this->stripe->createPaymentIntent($data, $this->stripe->stripe_connect_auth);

            SystemLogger::dispatch($response, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->stripe->client, $this->stripe->client->company);
        } catch (\Exception $e) {
            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => '',
                'param' => '',
                'message' => '',
            ];

            switch ($e) {
                case $e instanceof CardException:
                    $data['status'] = $e->getHttpStatus();
                    $data['error_type'] = $e->getError()->type;
                    $data['error_code'] = $e->getError()->code;
                    $data['param'] = $e->getError()->param;
                    $data['message'] = $e->getError()->message;
                break;
                case $e instanceof RateLimitException:
                    $data['message'] = 'Too many requests made to the API too quickly';
                break;
                case $e instanceof InvalidRequestException:
                    $data['message'] = 'Invalid parameters were supplied to Stripe\'s API';
                break;
                case $e instanceof AuthenticationException:
                    $data['message'] = 'Authentication with Stripe\'s API failed';
                break;
                case $e instanceof ApiErrorException:
                    $data['message'] = 'Network communication with Stripe failed';
                break;

                default:
                    $data['message'] = $e->getMessage();
                break;
            }

            $this->stripe->processInternallyFailedPayment($this->stripe, $e);

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client, $this->stripe->client->company);
        }

        if (! $response) {
            return false;
        }

        if ($cgt->gateway_type_id == GatewayType::SEPA) {
            $payment_method_type = PaymentType::SEPA;
        } else {
            $payment_method_type = $response->charges->data[0]->payment_method_details->card->brand;
        }

        $data = [
            'gateway_type_id' => $cgt->gateway_type_id,
            'payment_type' => $this->transformPaymentTypeToConstant($payment_method_type),
            'transaction_reference' => $response->charges->data[0]->id,
            'amount' => $amount,
        ];

        $payment = $this->stripe->createPayment($data);
        $payment->meta = $cgt->meta;
        $payment->save();

        $payment_hash->payment_id = $payment->id;
        $payment_hash->save();

        return $payment;
    }

    private function formatGatewayResponse($data, $vars)
    {
        $response = $data['response'];

        return [
            'transaction_reference' => $response->getTransactionResponse()->getTransId(),
            'amount' => $vars['amount'],
            'auth_code' => $response->getTransactionResponse()->getAuthCode(),
            'code' => $response->getTransactionResponse()->getMessages()[0]->getCode(),
            'description' => $response->getTransactionResponse()->getMessages()[0]->getDescription(),
            'invoices' => $vars['hashed_ids'],
        ];
    }

    private function transformPaymentTypeToConstant($type)
    {
        switch ($type) {
            case 'visa':
                return PaymentType::VISA;
                break;
            case 'mastercard':
                return PaymentType::MASTERCARD;
                break;
            case PaymentType::SEPA:
                return PaymentType::SEPA;
            default:
                return PaymentType::CREDIT_CARD_OTHER;
                break;
        }
    }
}
