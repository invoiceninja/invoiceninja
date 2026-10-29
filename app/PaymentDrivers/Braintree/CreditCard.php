<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Braintree;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\BraintreePaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class CreditCard implements LivewireMethodInterface
{
    use MakesHash;
    /**
     * @var BraintreePaymentDriver
     */
    private $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->braintree;
        $data['threeds_enable'] = $this->braintree->company_gateway->getConfigField('threeds') ? "true" : "false";

        return render('gateways.braintree.credit_card.authorize', $data);
    }

    public function authorizeResponse($data): \Illuminate\Http\RedirectResponse
    {
        return back();
    }

    /**
     * Credit card payment page.
     *
     * @param array $data
     * @return array
     */

    private function threeDParameters(array $data)
    {
        return [
            'amount' => $data['amount_with_fee'],
            'email' => $this->braintree->client->present()->email(),
            'billingAddress' => [
                'givenName' => $this->braintree->client->present()->first_name() ?: $this->braintree->client->present()->name(),
                'surname' => $this->braintree->client->present()->last_name() ?: '',
                'phoneNumber' => $this->braintree->client->present()->phone(),
                'streetAddress' => $this->braintree->client->address1 ?: '',
                'extendedAddress' => $this->braintree->client->address2 ?: '',
                'locality' => $this->braintree->client->city ?: '',
                'region' => $this->braintree->client->state ?: '',
                'postalCode' => $this->braintree->client->postal_code ?: '',
                'countryCodeAlpha2' => $this->braintree->client->country ? $this->braintree->client->country->iso_3166_2 : 'US',
            ],
        ];
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.braintree.credit_card.pay', $data);
    }

    /**
     * Process the credit card payments.
     *
     * @param PaymentResponseRequest $request
     * @return \Illuminate\Http\RedirectResponse|void
     * @throws PaymentFailed
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->braintree->client->fresh();

        $state = [
            'server_response' => json_decode($request->gateway_response ?? ''),
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $this->braintree->payment_hash->data = array_merge((array) $this->braintree->payment_hash->data, $state);
        $this->braintree->payment_hash->save();

        $customer = $this->braintree->findOrCreateCustomer();

        $payment_source = $this->paymentSource($request, $customer->id);

        $total_taxes = \App\Models\Invoice::query()->whereIn('id', $this->transformKeys(array_column($this->braintree->payment_hash->invoices(), 'invoice_id')))->withTrashed()->sum('total_taxes');
        $invoice = $this->braintree->payment_hash->fee_invoice;
        $po_number = $invoice->po_number ?? $invoice->number ?? '';

        $data = [
            'amount' => $this->braintree->payment_hash->data->amount_with_fee, //@phpstan-ignore-line
            'deviceData' => $state['client-data'],
            'options' => [
                'submitForSettlement' => true,
            ],
            'channel' => 'invoiceninja_BT',
            'billing' => [
                'streetAddress' => $this->braintree->client->address1 ?: '',
                'extendedAddress' => $this->braintree->client->address2 ?: '',
                'locality' => $this->braintree->client->city ?: '',
                'postalCode' => $this->braintree->client->postal_code ?: '',
                'countryCodeAlpha2' => $this->braintree->client->country ? $this->braintree->client->country->iso_3166_2 : 'US',
            ],
            'taxAmount' => $total_taxes,
            'purchaseOrderNumber' => substr($po_number, 0, 16),
        ];

        $data = array_replace_recursive($data, $payment_source);
        $data = array_merge($data, $this->braintree->getLevel23Data($this->braintree->payment_hash));

        if ($this->braintree->company_gateway->getConfigField('merchantAccountId')) {
            /** https://developer.paypal.com/braintree/docs/reference/request/transaction/sale/php#full-example */
            $data['merchantAccountId'] = $this->braintree->company_gateway->getConfigField('merchantAccountId');
        }

        try {
            $result = $this->braintree->gateway->transaction()->sale($data);
        } catch (\Exception $e) {
            if ($e instanceof \Braintree\Exception\Authorization) {
                $this->braintree->sendFailureMail(ctrans('texts.generic_gateway_error'));

                throw new PaymentFailed(ctrans('texts.generic_gateway_error'), $e->getCode());
            }

            $this->braintree->sendFailureMail($e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        if ($result->success) {
            $this->braintree->logSuccessfulGatewayResponse(['response' => $request->server_response, 'data' => $this->braintree->payment_hash->data], SystemLog::TYPE_BRAINTREE);

            if ($request->boolean('store_card') && ! $request->filled('token')) {
                $payment_method = $this->braintree->gateway->paymentMethod()->find($result->transaction->creditCard['token']);

                $this->storePaymentMethod($payment_method, $customer->id);
            }

            return $this->processSuccessfulPayment($result);
        }

        $error = $result ?: 'Undefined gateway error';

        return $this->processUnsuccessfulPayment($error);
    }

    private function paymentSource(PaymentResponseRequest $request, string $customer_id): array
    {
        $threeds_enabled = (bool) $this->braintree->company_gateway->getConfigField('threeds');

        if ($request->filled('token')) {
            $this->braintree->resolveStoredToken($request->token, GatewayType::CREDIT_CARD);

            if (! $threeds_enabled) {
                return ['paymentMethodToken' => $request->token];
            }
        }

        $gateway_response = json_decode($request->input('gateway_response') ?? '', true);
        $nonce = $gateway_response['nonce'] ?? null;

        if (! is_string($nonce) || $nonce === '') {
            throw new PaymentFailed(ctrans('texts.unable_to_verify_payment_method'));
        }

        if ($threeds_enabled) {
            // Never trust liability-shift flags posted by the browser.
            $payment_nonce = $this->braintree->gateway->paymentMethodNonce()->find($nonce);

            if (! ($payment_nonce->threeDSecureInfo->liabilityShifted ?? false)) {
                throw new PaymentFailed(ctrans('texts.unable_to_verify_payment_method'));
            }
        }

        $data = [
            'paymentMethodNonce' => $nonce,
            'options' => ['threeDSecure' => ['required' => $threeds_enabled]],
        ];

        if (! $request->filled('token') && $request->boolean('store_card')) {
            $data['customerId'] = $customer_id;
            $data['options']['storeInVaultOnSuccess'] = true;
        }

        return $data;
    }

    private function processSuccessfulPayment($response)
    {
        $state = $this->braintree->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::parseCardType(strtolower($response->transaction->creditCard['cardType'])),
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction->id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->braintree->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->braintree->encodePrimaryKey($payment->id)]);
    }

    /**
     * @throws PaymentFailed
     */
    private function processUnsuccessfulPayment($response)
    {
        $this->braintree->sendFailureMail($response?->transaction?->additionalProcessorResponse);

        $message = [
            'server_response' => $response,
            'data' => $this->braintree->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        throw new PaymentFailed($response?->transaction?->additionalProcessorResponse ?: 'Unhandled error, please contact merchant', $response?->transaction?->processorResponseCode ?: 500);
    }

    private function storePaymentMethod($method, $customer_reference)
    {
        try {
            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) $method->expirationMonth;
            $payment_meta->exp_year = (string) $method->expirationYear;
            $payment_meta->brand = (string) $method->cardType;
            $payment_meta->last4 = (string) $method->last4;
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->token,
                'payment_method_id' => $this->braintree->payment_hash->data->payment_method_id,
            ];

            $this->braintree->storeGatewayToken($data, ['gateway_customer_reference' => $customer_reference]);
        } catch (\Exception $e) {
            return $this->braintree->processInternallyFailedPayment($this->braintree, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.braintree.credit_card.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->braintree;
        $data['threeds'] = $this->threeDParameters($data);
        $data['threeds_enable'] = $this->braintree->company_gateway->getConfigField('threeds') ? "true" : "false";

        $token_options = [];
        if ($this->braintree->company_gateway->getConfigField('merchantAccountId')) {
            $token_options['merchantAccountId'] = $this->braintree->company_gateway->getConfigField('merchantAccountId');
        }
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate($token_options);
        $data['saved_cards'] = [];

        if ($this->braintree->company_gateway->getConfigField('threeds')) {
            foreach ($data['tokens'] ?? [] as $token) {
                $this->braintree->resolveStoredToken($token->token, GatewayType::CREDIT_CARD);
                try {
                    $result = $this->braintree->gateway->paymentMethodNonce()->create($token->token);

                    if (! $result->success) {
                        continue;
                    }

                    $card = $this->braintree->gateway->paymentMethod()->find($token->token);
                } catch (\Braintree\Exception\NotFound $e) {
                    // A card removed from the vault must not prevent paying with a new card.
                    continue;
                }

                $data['saved_cards'][$token->token] = ['nonce' => $result->paymentMethodNonce->nonce, 'bin' => $card->bin];
            }

            $data['tokens'] = collect($data['tokens'] ?? [])
                ->filter(fn($token) => isset($data['saved_cards'][$token->token]))
                ->values();
        }

        return $data;
    }
}
