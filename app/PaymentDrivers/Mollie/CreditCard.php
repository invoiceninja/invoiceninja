<?php

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Mollie\Api\Exceptions\ApiException;

class CreditCard extends MolliePaymentMethod implements MethodInterface, LivewireMethodInterface
{
    protected const MOLLIE_PAYMENT_METHOD = 'creditcard';

    protected const GATEWAY_TYPE_ID = GatewayType::CREDIT_CARD;

    protected const PAYMENT_TYPE_ID = PaymentType::CREDIT_CARD_OTHER;

    protected const AUTHORIZE_VIEW_TEMPLATE = 'gateways.mollie.credit_card.authorize';

    /** @inheritDoc */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.mollie.credit_card.pay', $data);
    }

    /** @inheritDoc */
    public function paymentResponse(PaymentResponseRequest $request): \Illuminate\Http\Response|RedirectResponse
    {
        $amount = $this->mollie->convertToMollieAmount((float) $this->mollie->payment_hash->data->amount_with_fee);

        $description = sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($this->mollie->payment_hash->invoices())->pluck('invoice_number')->toArray()));

        $this->mollie->payment_hash
            ->withData('gateway_type_id', GatewayType::CREDIT_CARD)
            ->withData('client_id', $this->mollie->client->id);

        if (!empty($request->token)) {
            try {
                $cgt = ClientGatewayToken::query()
                    ->where('token', $request->token)
                    ->where('client_id', $this->mollie->client->id)
                    ->firstOrFail();

                $molliePayment = $this->mollie->gateway->payments->create([
                    'method' => 'creditcard',
                    'amount' => [
                        'currency' => $this->mollie->client->currency()->code,
                        'value' => $amount,
                    ],
                    'mandateId' => $request->token,
                    'customerId' => $cgt->gateway_customer_reference,
                    'sequenceType' => 'recurring',
                    'description' => $description,
                    'webhookUrl'  => $this->mollie->company_gateway->webhookUrl(),
                    // 'idempotencyKey' => uniqid("st", true),
                    'metadata' => [
                        'client_id' => $this->mollie->client->hashed_id,
                        'hash' => $this->mollie->payment_hash->hash,
                        'gateway_type_id' => GatewayType::CREDIT_CARD,
                        'payment_type_id' => PaymentType::CREDIT_CARD_OTHER,
                    ],
                ]);

                if ($molliePayment->status === 'paid') {
                    return $this->processSuccessfulPayment($molliePayment);
                }

                if ($molliePayment->status === 'open') {
                    $this->mollie->payment_hash->withData('transaction_reference', $molliePayment->id);

                    if (!$molliePayment->getCheckoutUrl()) {
                        return render('gateways.mollie.mollie_placeholder');
                    } else {
                        return redirect()->away($molliePayment->getCheckoutUrl());
                    }
                }
            } catch (\Throwable $e) {
                return $this->processUnsuccessfulPayment($e);
            }
        }

        try {
            $data = [
                'method' => 'creditcard',
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $amount,
                ],
                'description' => $description,
                // 'idempotencyKey' => uniqid("st", true),
                'redirectUrl' => route('mollie.3ds_redirect', [
                    'company_key' => $this->mollie->client->company->company_key,
                    'company_gateway_id' => $this->mollie->company_gateway->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                ]),
                'webhookUrl'  => $this->mollie->company_gateway->webhookUrl(),
                'metadata' => [
                    'client_id' => $this->mollie->client->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                    'gateway_type_id' => GatewayType::CREDIT_CARD,
                    'payment_type_id' => PaymentType::CREDIT_CARD_OTHER,
                ],
                'cardToken' => $request->gateway_response,
            ];

            if ($request->shouldStoreToken()) {
                // Check if a mollie CustomerId already exists for this client, if so, use that
                $gateway_customer_reference = null;
                if ($this->mollie->client->gateway_tokens->count() > 0) {
                    $gateway_customer_reference = $this->mollie->client->gateway_tokens->first()->gateway_customer_reference;
                }
                if (!$gateway_customer_reference) {
                    $customer = $this->mollie->gateway->customers->create([
                        'name' => $this->mollie->client->name,
                        'email' => $this->mollie->client->present()->email(),
                        'metadata' => [
                            'id' => $this->mollie->client->hashed_id,
                        ],
                    ]);
                    $gateway_customer_reference = $customer->id;
                }

                $data['customerId'] = $gateway_customer_reference;
                $data['sequenceType'] = 'first';

                $this->mollie->payment_hash
                    ->withData('mollieCustomerId', $gateway_customer_reference)
                    ->withData('shouldStoreToken', true);
            }

            $molliePayment = $this->mollie->gateway->payments->create($data);

            if ($molliePayment->status === 'paid') {
                return $this->processSuccessfulPayment($molliePayment);
            }

            if ($molliePayment->status === 'open') {
                $this->mollie->payment_hash->withData('transaction_reference', $molliePayment->id);

                if (!$molliePayment->getCheckoutUrl()) {
                    return response()->render('gateways.mollie.mollie_placeholder');
                } else {
                    return redirect()->away($molliePayment->getCheckoutUrl());
                }
            }
        } catch (\Exception $e) {
            return $this->processUnsuccessfulPayment($e);
        }
        return response()->render('gateways.mollie.mollie_placeholder');
    }

    /** @inheritDoc */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.mollie.credit_card.pay_livewire';
    }

    /** @inheritDoc */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->mollie;

        return $data;
    }
}
