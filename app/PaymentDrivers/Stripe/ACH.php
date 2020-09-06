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

namespace App\PaymentDrivers\Stripe;

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;
use Stripe\Exception\InvalidRequestException;

class ACH
{
    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        return render('gateways.stripe.ach.authorize', array_merge($data));
    }

    public function authorizeResponse($request)
    {
        $state = [
            'server_response' => json_decode($request->gateway_response),
            'gateway_id' => $request->company_gateway_id,
            'gateway_type_id' => $request->gateway_type_id,
            'is_default' => $request->is_default,
        ];

        $customer = $this->stripe->findOrCreateCustomer();

        $this->stripe->init();

        $local_stripe = new \Stripe\StripeClient(
            $this->stripe->company_gateway->getConfigField('apiKey')
        );

        try {
            $local_stripe->customers->createSource(
                $customer->id,
                ['source' => $state['server_response']->token->id]
            );
        } catch (InvalidRequestException $e) {
            return back()->with('ach_error', $e->getMessage());
        }

        $payment_meta = $state['server_response']->token->bank_account;
        $payment_meta->brand = ctrans('texts.ach');
        $payment_meta->type = ctrans('texts.bank_transfer');
        $payment_meta->verified_at = null;
        $payment_meta->token = $state['server_response']->token->id;

        $client_gateway_token = new ClientGatewayToken();
        $client_gateway_token->company_id = $this->stripe->client->company->id;
        $client_gateway_token->client_id = $this->stripe->client->id;
        $client_gateway_token->token = $state['server_response']->token->bank_account->id;
        $client_gateway_token->company_gateway_id = $this->stripe->company_gateway->id;
        $client_gateway_token->gateway_type_id = $state['gateway_type_id'];
        $client_gateway_token->gateway_customer_reference = $customer->id;
        $client_gateway_token->meta = $payment_meta;
        $client_gateway_token->save();

        if ($state['is_default'] == 'true' || $this->stripe->client->gateway_tokens->count() == 1) {
            $this->stripe->client->gateway_tokens()->update(['is_default' => 0]);

            $client_gateway_token->is_default = 1;
            $client_gateway_token->save();
        }

        return redirect()->route('client.payment_methods.verification', ['id' => $client_gateway_token->hashed_id, 'method' => GatewayType::BANK_TRANSFER]);
    }

    public function verificationView(ClientGatewayToken $token)
    {
        return render('gateways.stripe.ach.verify', compact('token'));
    }

    public function processVerification(ClientGatewayToken $token)
    {
        $this->stripe->init();

        $bank_account = \Stripe\Customer::retrieveSource(
            request()->customer,
            request()->source,
        );

        try {
            $status = $bank_account->verify(['amounts' => request()->transactions]);

            $token->meta->verified_at = now();
            $token->save();

            return redirect()
                ->route('client.invoices.index')
                ->with('success', __('texts.payment_method_verified'));
        } catch (\Stripe\Exception\CardException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function paymentView(array $data)
    {
        $state = [
            'amount' => $data['amount_with_fee'],
            'currency' => $this->stripe->client->getCurrencyCode(),
            'invoices' => $data['invoices'],
            'gateway' => $this->stripe,
            'payment_method_id' => GatewayType::BANK_TRANSFER,
            'token' => $data['token'],
            'customer' => $this->stripe->findOrCreateCustomer(),
        ];

        return render('gateways.stripe.ach.pay', $state);
    }

    public function paymentResponse($request)
    {
        $state = [
            'payment_method' => $request->payment_method_id,
            'gateway_type_id' => $request->company_gateway_id,
            'hashed_ids' => $request->hashed_ids,
            'amount' => $this->stripe->convertToStripeAmount($request->amount, $this->stripe->client->currency()->precision),
            'currency' => $request->currency,
            'source' => $request->source,
            'customer' => $request->customer,
        ];

        if ($this->stripe->getContact()) {
            $state['client_contact'] = $this->stripe->getContact();
        } else {
            $state['client_contact'] = $state['invoices']->first()->invitations->first()->contact;
        }

        $this->stripe->init();

        try {
            $state['charge'] = \Stripe\Charge::create([
                'amount' => $state['amount'],
                'currency' => $state['currency'],
                'customer' => $state['customer'],
                'source' => $state['source'],
            ]);

            if ($state['charge']->status === 'pending' && is_null($state['charge']->failure_message)) {
                return $this->processPendingPayment($state);
            }

            return $this->processUnsuccessfulPayment($state);
        } catch (\Exception $e) {
            if ($e instanceof \Stripe\Exception\CardException) {
                return redirect()->route('client.payment_methods.verification', ['id' => ClientGatewayToken::first()->hashed_id, 'method' => GatewayType::BANK_TRANSFER]);
            }
        }
    }

    public function processPendingPayment($state)
    {
        $state['charge_id'] = $state['charge']->id;

        $this->stripe->init();

        $state['payment_type'] = PaymentType::ACH;

        $data = [
            'payment_method' => $state['charge_id'],
            'payment_type' => $state['payment_type'],
            'amount' => $state['charge']->amount,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        $this->stripe->attachInvoices($payment, $state['hashed_ids']);

        $payment->service()->updateInvoicePayment();

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        $logger_message = [
            'server_response' => $state['charge'],
            'data' => $data,
        ];

        SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->stripe->client);

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($state)
    {
        PaymentFailureMailer::dispatch($this->stripe->client, $state['charge']->failure_message, $this->stripe->client->company, $state['amount']);

        $message = [
            'server_response' => $state['charge'],
            'data' => $state,
        ];

        SystemLogger::dispatch($message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        throw new \Exception('Failed to process the payment.', 1);
    }
}
