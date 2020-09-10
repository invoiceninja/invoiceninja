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
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;
use Stripe\PaymentMethod;

class CreditCard
{
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        $intent['intent'] = $this->stripe->getSetupIntent();

        return render('gateways.stripe.add_credit_card', array_merge($data, $intent));
    }

    public function authorizeResponse($request)
    {
        $server_response = json_decode($request->input('gateway_response'));

        $gateway_id = $request->input('gateway_id');
        $gateway_type_id = $request->input('payment_method_id');
        $is_default = $request->input('is_default');

        $payment_method = $server_response->payment_method;

        $customer = $this->stripe->findOrCreateCustomer();

        $this->stripe->init();

        $stripe_payment_method = \Stripe\PaymentMethod::retrieve($payment_method);
        $stripe_payment_method_obj = $stripe_payment_method->jsonSerialize();
        $stripe_payment_method->attach(['customer' => $customer->id]);

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = $stripe_payment_method_obj['card']['exp_month'];
        $payment_meta->exp_year = $stripe_payment_method_obj['card']['exp_year'];
        $payment_meta->brand = $stripe_payment_method_obj['card']['brand'];
        $payment_meta->last4 = $stripe_payment_method_obj['card']['last4'];
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $client_gateway_token = new ClientGatewayToken();
        $client_gateway_token->company_id = $this->stripe->client->company->id;
        $client_gateway_token->client_id = $this->stripe->client->id;
        $client_gateway_token->token = $payment_method;
        $client_gateway_token->company_gateway_id = $this->stripe->company_gateway->id;
        $client_gateway_token->gateway_type_id = $gateway_type_id;
        $client_gateway_token->gateway_customer_reference = $customer->id;
        $client_gateway_token->meta = $payment_meta;
        $client_gateway_token->save();

        if ($is_default == 'true' || $this->stripe->client->gateway_tokens->count() == 1) {
            $this->stripe->client->gateway_tokens()->update(['is_default' => 0]);

            $client_gateway_token->is_default = 1;
            $client_gateway_token->save();
        }

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
        $payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($data['amount_with_fee'], $this->stripe->client->currency()->precision),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => collect($data['invoices'])->pluck('id'), //todo more meaningful description here:
        ];

        if ($data['token']) {
            $payment_intent_data['payment_method'] = $data['token']->token;
        } else {
            $payment_intent_data['setup_future_usage'] = 'off_session';
            // $payment_intent_data['save_payment_method'] = true;
            // $payment_intent_data['confirm'] = true;
        }

        $data['intent'] = $this->stripe->createPaymentIntent($payment_intent_data);
        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.credit_card', $data);
    }

    public function paymentResponse($request)
    {
        $server_response = json_decode($request->input('gateway_response'));

        $payment_hash = PaymentHash::whereRaw('BINARY `hash`= ?', [$request->input('payment_hash')])->firstOrFail();

        $state = [
            'payment_method' => $server_response->payment_method,
            'payment_status' => $server_response->status,
            'save_card' => $request->store_card,
            'gateway_type_id' => $request->payment_method_id,
            'hashed_ids' => $request->hashed_ids,
            'server_response' => $server_response,
            'payment_hash' => $payment_hash,
        ];

        /*Hydrate the invoices from the payment hash*/
        $invoices = Invoice::whereIn('id', $this->stripe->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))
            ->whereClientId($this->stripe->client->id)
            ->get();

        if ($this->stripe->getContact()) {
            $client_contact = $this->stripe->getContact();
        } else {
            $client_contact = $invoices->first()->invitations->first()->contact;
        }

        $this->stripe->init();

        $state['payment_intent'] = \Stripe\PaymentIntent::retrieve($server_response->id);
        $state['customer'] = $state['payment_intent']->customer;

        if ($state['payment_status'] == 'succeeded') {

            /* Add gateway fees if needed! */
            $this->stripe->confirmGatewayFee($request);

            return $this->processSuccessfulPayment($state);
        }

        return $this->processUnsuccessfulPayment($server_response);
    }

    private function processSuccessfulPayment($state)
    {
        $state['charge_id'] = $state['payment_intent']->charges->data[0]->id;

        $this->stripe->init();

        $state['payment_method'] = PaymentMethod::retrieve($state['payment_method']);
        $payment_method_object = $state['payment_method']->jsonSerialize();

        $state['payment_meta'] = [
            'exp_month' => $payment_method_object['card']['exp_month'],
            'exp_year' => $payment_method_object['card']['exp_year'],
            'brand' => $payment_method_object['card']['brand'],
            'last4' => $payment_method_object['card']['last4'],
            'type' => $payment_method_object['type'],
        ];

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = $payment_method_object['card']['exp_month'];
        $payment_meta->exp_year = $payment_method_object['card']['exp_year'];
        $payment_meta->brand = $payment_method_object['card']['brand'];
        $payment_meta->last4 = $payment_method_object['card']['last4'];
        $payment_meta->type = $payment_method_object['type'];

        $payment_type = PaymentType::parseCardType($payment_method_object['card']['brand']);

        if ($state['save_card'] == true) {
            $this->saveCard($state);
        }

        // Todo: Need to fix this to support payment types other than credit card.... sepa etc etc
        if (! isset($state['payment_type'])) {
            $state['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        }

        $data = [
            'payment_method' => $state['charge_id'],
            'payment_type' => $state['payment_type'],
            'amount' => $state['server_response']->amount,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->stripe->createPayment($data, $status = Payment::STATUS_COMPLETED);
        $payment->meta = $payment_meta;
        $payment->save();

        $payment_hash = $state['payment_hash'];
        $payment_hash->payment_id = $payment->id;
        $payment_hash->save();

        $payment = $this->stripe->attachInvoices($payment, $state['payment_hash']);

        $payment->service()->updateInvoicePayment($state['payment_hash']);

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        $logger_message = [
            'server_response' => $state['payment_intent'],
            'data' => $data,
        ];

        SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->stripe->client);

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($server_response)
    {
        PaymentFailureMailer::dispatch($this->stripe->client, $server_response->cancellation_reason, $this->stripe->client->company, $server_response->amount);

        $message = [
            'server_response' => $server_response,
            'data' => [],
        ];

        SystemLogger::dispatch($message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        throw new \Exception('Failed to process the payment.', 1);
    }

    private function saveCard($state)
    {
        $state['payment_method']->attach(['customer' => $state['customer']]);

        $company_gateway_token = new ClientGatewayToken();
        $company_gateway_token->company_id = $this->stripe->client->company->id;
        $company_gateway_token->client_id = $this->stripe->client->id;
        $company_gateway_token->token = $state['payment_method']->id;
        $company_gateway_token->company_gateway_id = $this->stripe->company_gateway->id;
        $company_gateway_token->gateway_type_id = $state['gateway_type_id'];
        $company_gateway_token->gateway_customer_reference = $state['customer'];
        $company_gateway_token->meta = $state['payment_meta'];
        $company_gateway_token->save();

        if ($this->stripe->client->gateway_tokens->count() == 1) {
            $this->stripe->client->gateway_tokens()->update(['is_default' => 0]);

            $company_gateway_token->is_default = 1;
            $company_gateway_token->save();
        }
    }
}
