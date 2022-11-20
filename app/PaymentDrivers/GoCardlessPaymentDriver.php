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

use App\Events\Payment\PaymentFailed;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;

class GoCardlessPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public \GoCardlessPro\Client $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::BANK_TRANSFER => \App\PaymentDrivers\GoCardless\ACH::class,
        GatewayType::DIRECT_DEBIT => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::SEPA => \App\PaymentDrivers\GoCardless\SEPA::class,
        GatewayType::INSTANT_BANK_PAY => \App\PaymentDrivers\GoCardless\InstantBankPay::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_GOCARDLESS;

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [];

        if (
            $this->client
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['USA'])
        ) {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        if (
            $this->client
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['GBR'])
        ) {
            $types[] = GatewayType::DIRECT_DEBIT;
        }

        if ($this->client->currency()->code === 'EUR') {
            $types[] = GatewayType::SEPA;
        }

        if ($this->client->currency()->code === 'GBP') {
            $types[] = GatewayType::INSTANT_BANK_PAY;
        }

        return $types;
    }

    public function init(): self
    {
        $this->gateway = new \GoCardlessPro\Client([
            'access_token' => $this->company_gateway->getConfigField('accessToken'),
            'environment'  => $this->company_gateway->getConfigField('testMode') ? \GoCardlessPro\Environment::SANDBOX : \GoCardlessPro\Environment::LIVE,
        ]);

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
        // ..
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $converted_amount = $this->convertToGoCardlessAmount($amount, $this->client->currency()->precision);

        $this->init();

        try {
            $payment = $this->gateway->payments()->create([
                'params' => [
                    'amount' => $converted_amount,
                    'currency' => $this->client->getCurrencyCode(),
                    'metadata' => [
                        'payment_hash' => $this->payment_hash->hash,
                    ],
                    'links' => [
                        'mandate' => $cgt->token,
                    ],
                ],
            ]);

            if ($payment->status === 'pending_submission') {
                $this->confirmGatewayFee();

                $data = [
                    'payment_method' => $cgt->hashed_id,
                    'payment_type' => PaymentType::ACH,
                    'amount' => $amount,
                    'transaction_reference' => $payment->id,
                    'gateway_type_id' => GatewayType::BANK_TRANSFER,
                ];

                $payment = $this->createPayment($data, Payment::STATUS_PENDING);

                SystemLogger::dispatch(
                    ['response' => $payment, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_GOCARDLESS,
                    $this->client,
                    $this->client->company
                );

                return $payment;
            }

            $this->sendFailureMail($payment->status);

            $message = [
                'server_response' => $payment,
                'data' => $payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );

            return false;
        } catch (\Exception $exception) {
            $this->unWindGatewayFees($this->payment_hash);

            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => $exception->getCode(),
                'param' => '',
                'message' => $exception->getMessage(),
            ];

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_GOCARDLESS, $this->client, $this->client->company);
        }
    }

    public function convertToGoCardlessAmount($amount, $precision)
    {
        return \round(($amount * pow(10, $precision)), 0);
    }

    public function detach(ClientGatewayToken $token)
    {
        $this->init();

        try {
            $this->gateway->mandates()->cancel($token->token);
        } catch (\Exception $e) {
            nlog($e->getMessage());

            SystemLogger::dispatch(
                [
                    'server_response' => $e->getMessage(),
                    'data' => request()->all(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        // Allow app to catch up with webhook request.
        $this->init();

        nlog('GoCardless Event');
        nlog($request->all());

        if (! $request->has('events')) {
            nlog('No GoCardless events to process in response?');

            return response()->json([], 200);
        }

        sleep(1);

        foreach ($request->events as $event) {
            if ($event['action'] === 'confirmed' || $event['action'] === 'paid_out') {
                nlog('Searching for transaction reference');

                $payment = Payment::query()
                    ->where('transaction_reference', $event['links']['payment'])
                    ->where('company_id', $request->getCompany()->id)
                    ->first();

                if ($payment) {
                    $payment->status_id = Payment::STATUS_COMPLETED;
                    $payment->save();
                    nlog('GoCardless completed');
                } else {
                    nlog('I was unable to find the payment for this reference');
                }
                //finalize payments on invoices here.
            }

            if ($event['action'] === 'failed' && array_key_exists('payment', $event['links'])) {
                $payment = Payment::query()
                    ->where('transaction_reference', $event['links']['payment'])
                    ->where('company_id', $request->getCompany()->id)
                    ->first();

                if ($payment) {
                    $payment->status_id = Payment::STATUS_FAILED;
                    $payment->save();
                    nlog('GoCardless completed');
                }
            }

            //billing_request fulfilled
            //

            //i need to build more context here, i need the client , the payment hash resolved and update the class properties.
            //after i resolve the payment hash, ensure the invoice has not been marked as paid and the payment does not already exist.
            //if it does exist, ensure it is completed and not pending.

            if($event['action'] == 'fulfilled' && array_key_exists('billing_request', $event['links'])) {

                $hash = PaymentHash::whereJsonContains('data->billing_request', $event['links']['billing_request'])->first();

                if(!$hash){
                    nlog("GoCardless: couldn't find a hash, need to abort => Billing Request => " . $event['links']['billing_request']);
                    return response()->json([], 200);
                }

                $this->setPaymentHash($hash);

                $billing_request = $this->gateway->billingRequests()->get(
                    $event['links']['billing_request']
                );

                $payment = $this->gateway->payments()->get(
                    $billing_request->payment_request->links->payment
                );

                if ($billing_request->status === 'fulfilled') {

                    $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($hash->invoices(), 'invoice_id')))->withTrashed()->get();

                    $this->client = $invoices->first()->client;

                    $invoices->each(function ($invoice){

                        //if payments exist already, they just need to be confirmed.
                        if($invoice->payments()->exists()){
                            
                            $invoice->payments()->where('status_id', 1)->cursor()->each(function ($payment){
                                $payment->status_id = 4;
                                $payment->save();
                            });

                        }
                    });

                    // remove all paid invoices
                    $invoices->filter(function ($invoice){
                        return $invoice->isPayable();
                    });

                    //return early if nothing to do
                    if($invoices->count() == 0){
                        nlog("GoCardless: Could not harvest any invoices - probably all paid!!");
                        return response()->json([], 200);
                    }

                    $this->processSuccessfulPayment($payment);
                }

            }

        }

        return response()->json([], 200);
    }


    public function processSuccessfulPayment(\GoCardlessPro\Resources\Payment $payment, array $data = [])
    {
        $data = [
            'payment_method' => $payment->links->mandate,
            'payment_type' => PaymentType::INSTANT_BANK_PAY,
            'amount' => $this->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id,
            'gateway_type_id' => GatewayType::INSTANT_BANK_PAY,
        ];

        $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->save();

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_GOCARDLESS,
            $this->client,
            $this->client->company,
        );

    }




    public function ensureMandateIsReady($token)
    {
        try {
            $this->init();
            $mandate = $this->gateway->mandates()->get($token);

            if ($mandate->status !== 'active') {
                throw new \Exception(ctrans('texts.gocardless_mandate_not_ready'));
            }
        } catch (\Exception $exception) {
            throw new \App\Exceptions\PaymentFailed($exception->getMessage());
        }
    }
}
