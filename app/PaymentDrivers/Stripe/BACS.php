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

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Stripe\Jobs\UpdateCustomer;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Number;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;

class BACS implements LivewireMethodInterface
{
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        $customer = $this->stripe->findOrCreateCustomer();
        $data['session'] = Session::create([
            'payment_method_types' => ['bacs_debit'],
            'mode' => 'setup',
            'customer' => $customer->id,
            'success_url' => str_replace("%7B", "{", str_replace("%7D", "}", $this->buildAuthorizeUrl())),
            'cancel_url' => route('client.payment_methods.index'),
        ], $this->stripe->stripe_connect_auth);
        return render('gateways.stripe.bacs.authorize', $data);
    }
    private function buildAuthorizeUrl(): string
    {
        return route('client.payment_methods.confirm', [
            'method' => GatewayType::BACS,
            'session_id' => "{CHECKOUT_SESSION_ID}",
        ]);
    }

    public function authorizeResponse($request)
    {
        $this->stripe->init();
        if ($request->session_id) {
            $session = Session::retrieve([
                'id' => $request->session_id,
                'expand' => ['setup_intent'],
            ], $this->stripe->stripe_connect_auth);
            $customer = $this->stripe->findOrCreateCustomer();
            $intent = $session->setup_intent;

            if (
                $session->mode !== 'setup'
                || $session->status !== 'complete'
                || $this->stripeId($session->customer) !== $customer->id
                || $session->livemode !== $customer->livemode
                || ! ($intent instanceof \Stripe\SetupIntent)
                || $intent->status !== 'succeeded'
                || $intent->usage !== 'off_session'
                || $this->stripeId($intent->customer) !== $customer->id
                || $intent->livemode !== $customer->livemode
            ) {
                throw new PaymentFailed('Unable to validate the BACS setup.', 422);
            }

            $method_id = $this->stripeId($intent->payment_method);
            $mandate_id = $this->stripeId($intent->mandate);

            if (! $method_id || ! $mandate_id) {
                throw new PaymentFailed('The BACS setup is incomplete.', 422);
            }

            $method = $this->stripe->getStripePaymentMethod($method_id);
            $this->validateMethod($method, $customer);
            $this->storePaymentMethod($method, $customer, $mandate_id);
        }
        return redirect()->route('client.payment_methods.index');
    }
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.stripe.bacs.pay', $data);
    }
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->stripe->init();
        $invoice_numbers = collect($this->stripe->payment_hash->invoices())->pluck('invoice_number')->implode(',');
        // $description = ctrans('texts.stripe_payment_text', ['invoicenumber' => $invoice_numbers, 'amount' => Number::formatMoney($request->amount, $this->stripe->client), 'client' => $this->stripe->client->present()->name()]);
        $description = $this->stripe->getDescription(false);

        $payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($request->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $description,
            'payment_method_types' => ['bacs_debit'],
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::BACS,
            ],
            'payment_method' => $request->token,
            'confirm' => true,
        ];
        $state = [
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_intent' => $this->stripe->createPaymentIntent($payment_intent_data),
        ];
        $state = array_merge($state, $request->all());

        $state['customer'] = $state['payment_intent']->customer;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
        $this->stripe->payment_hash->save();

        if ($state['payment_intent']->status == 'processing') {
            $this->stripe->logSuccessfulGatewayResponse(['response' => $state['payment_intent'], 'data' => $this->stripe->payment_hash->data], SystemLog::TYPE_STRIPE);

            return $this->processSuccessfulPayment($state['payment_intent']);
        }

        return $this->processUnsuccessfulPayment("An unknown error occured.");
    }

    public function processSuccessfulPayment($payment_intent)
    {
        UpdateCustomer::dispatch($this->stripe->company_gateway->company->company_key, $this->stripe->company_gateway->id, $this->stripe->client->id);

        $data = [
            'payment_method' => $payment_intent['id'],
            'payment_type' => PaymentType::BACS,
            'amount' => $this->stripe->convertFromStripeAmount($payment_intent->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent['id'],
            'gateway_type_id' => GatewayType::BACS,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        (new SystemLogger(
            ['response' => $payment_intent, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        ))->handle();


        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($server_response)
    {
        $this->stripe->sendFailureMail($server_response);

        $message = [
            'server_response' => $server_response,
            'data' => $this->stripe->payment_hash->data,
        ];

        (new SystemLogger(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        ))->handle();

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    private function stripeId(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value !== '' ? $value : null;
        }

        return is_object($value) ? ($value->id ?? null) : null;
    }

    private function validateMethod(\Stripe\PaymentMethod $method, \Stripe\Customer $customer): void
    {
        if (
            $method->type !== 'bacs_debit'
            || $this->stripeId($method->customer) !== $customer->id
            || $method->livemode !== $customer->livemode
        ) {
            throw new PaymentFailed('Unable to validate the BACS account.', 422);
        }
    }

    private function mandateState(\Stripe\Mandate $mandate, \Stripe\PaymentMethod $method): string
    {
        $on_behalf_of = $this->stripeId($mandate->on_behalf_of ?? null);

        if (
            $mandate->type !== 'multi_use'
            || $this->stripeId($mandate->payment_method) !== $method->id
            || $mandate->livemode !== $method->livemode
            || ($on_behalf_of !== null && $on_behalf_of !== ($this->stripe->stripe_connect_auth['stripe_account'] ?? null))
        ) {
            throw new PaymentFailed('Unable to validate the BACS mandate.', 422);
        }

        return match ($mandate->status) {
            'active' => 'authorized',
            'pending' => 'pending',
            'inactive' => 'inactive',
            default => throw new PaymentFailed('Unknown BACS mandate status.', 422),
        };
    }

    private function tokenQuery(string $method_id, string $customer_id)
    {
        $client = $this->stripe->client;

        return ClientGatewayToken::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->where('company_gateway_id', $this->stripe->company_gateway->id)
            ->where('gateway_customer_reference', $customer_id)
            ->where('gateway_type_id', GatewayType::BACS)
            ->where('token', $method_id);
    }

    private function storePaymentMethod($method, $customer, string $mandate_id): void
    {
        $client = $this->stripe->client;
        try {
            DB::transaction(function () use ($client, $method, $customer, $mandate_id) {
                Client::query()->withoutEagerLoads()
                    ->where('company_id', $client->company_id)
                    ->whereKey($client->id)->lockForUpdate()->firstOrFail();
                // Fetch under the same lock used by mandate webhooks, preventing stale writes.
                $mandate = \Stripe\Mandate::retrieve($mandate_id, $this->stripe->stripe_connect_auth);
                $state = $this->mandateState($mandate, $method);
                $token = $this->tokenQuery($method->id, $customer->id)->first();

                if (! $token) {
                    $token = new ClientGatewayToken();
                    $token->company_id = $client->company_id;
                    $token->client_id = $client->id;
                    $token->company_gateway_id = $this->stripe->company_gateway->id;
                    $token->gateway_customer_reference = $customer->id;
                    $token->gateway_type_id = GatewayType::BACS;
                    $token->token = $method->id;
                    // Preserve the portal's existing behaviour: a newly added method is default.
                    ClientGatewayToken::query()
                        ->where('company_id', $client->company_id)
                        ->where('client_id', $client->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                    $token->is_default = true;
                }

                $token->meta = (object) array_merge((array) $token->meta, [
                    'brand' => (string) $method->bacs_debit->sort_code,
                    'last4' => (string) $method->bacs_debit->last4,
                    'type' => GatewayType::BACS,
                    'mandate_id' => $mandate->id,
                    'state' => $state,
                ]);
                if ($token->isDirty()) {
                    $token->save();
                }
            });
        } catch (\Exception $e) {
            $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }

    public function handleMandateUpdated(array $event): void
    {
        $this->stripe->init();
        $mandate_id = data_get($event, 'data.object.id');
        $method_id = data_get($event, 'data.object.payment_method');
        if (! is_string($mandate_id) || ! is_string($method_id)) {
            return;
        }

        $client = $this->stripe->client;
        DB::transaction(function () use ($client, $mandate_id, $method_id) {
            Client::query()->withoutEagerLoads()
                ->where('company_id', $client->company_id)
                ->whereKey($client->id)->lockForUpdate()->firstOrFail();
            $method = $this->stripe->getStripePaymentMethod($method_id);
            $customer_id = $this->stripeId($method->customer);
            if (! $customer_id) {
                return;
            }
            $token = $this->tokenQuery($method_id, $customer_id)->first();
            // Older mandates must not overwrite the state of a replacement mandate.
            if (! $token || (($token->meta->mandate_id ?? $mandate_id) !== $mandate_id)) {
                return;
            }
            $customer = $this->stripe->getCustomer($customer_id);
            $this->validateMethod($method, $customer);
            $mandate = \Stripe\Mandate::retrieve($mandate_id, $this->stripe->stripe_connect_auth);
            $state = $this->mandateState($mandate, $method);
            $token->meta = (object) array_merge((array) $token->meta, [
                'mandate_id' => $mandate->id,
                'state' => $state,
            ]);
            if ($token->isDirty()) {
                $token->save();
            }
        });
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->stripe;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['payment_hash'] = $this->stripe->payment_hash->hash;

        return $data;
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.bacs.pay_livewire';
    }
}
