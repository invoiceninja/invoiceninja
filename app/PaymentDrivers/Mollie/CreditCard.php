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

class CreditCard implements MethodInterface, LivewireMethodInterface
{
    /**
     * @var MolliePaymentDriver
     */
    protected $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    /** @inheritDoc */
    public function authorizeView(array $data): View
    {
        return render('gateways.mollie.credit_card.authorize', $data);
    }

    /** @inheritDoc */
    public function authorizeResponse($request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /** @inheritDoc */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.mollie.credit_card.pay', $data);
    }

    /**
     * @throws PaymentFailed When the payment processing fails
     * @inheritDoc
     */
    public function paymentResponse(PaymentResponseRequest $request): \Illuminate\Http\Response|RedirectResponse
    {
        $amount = $this->mollie->convertToMollieAmount((float) $this->mollie->payment_hash->data->amount_with_fee);

        $description = sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($this->mollie->payment_hash->invoices())->pluck('invoice_number')->toArray()));

        $this->mollie->payment_hash
            ->withData('gateway_type_id', GatewayType::CREDIT_CARD)
            ->withData('client_id', $this->mollie->client->id);

        if (! empty($request->token)) {
            try {
                $cgt = ClientGatewayToken::where('token', $request->token)->firstOrFail();

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
                    $this->mollie->logSuccessfulGatewayResponse(
                        ['response' => $molliePayment, 'data' => $this->mollie->payment_hash->data],
                        SystemLog::TYPE_MOLLIE
                    );

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
                $this->mollie->logSuccessfulGatewayResponse(
                    ['response' => $molliePayment, 'data' => $this->mollie->payment_hash->data],
                    SystemLog::TYPE_MOLLIE
                );

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
            $this->processUnsuccessfulPayment($e);

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
        return response()->render('gateways.mollie.mollie_placeholder');
    }

    /**
     * Process a successful credit card payment.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment The Mollie payment object
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSuccessfulPayment(\Mollie\Api\Resources\Payment $molliePayment): RedirectResponse
    {
        $payment_hash = $this->mollie->payment_hash;

        $this->mollie->createClientGatewayTokenFromMolliePayment($molliePayment);

        $data = [
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total,
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'transaction_reference' => $molliePayment->id,
        ];

        $payment_record = $this->mollie->createPayment($data, $molliePayment->status === 'paid' ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $molliePayment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->mollie->encodePrimaryKey($payment_record->id)]);
    }

    /**
     * Handle an unsuccessful payment attempt.
     *
     * @param \Throwable $e The exception that was thrown
     * @throws PaymentFailed Always throws a PaymentFailed exception
     * @return \Illuminate\Http\Response
     */
    public function processUnsuccessfulPayment(\Throwable $e): \Illuminate\Http\Response
    {
        $this->mollie->sendFailureMail($e->getMessage());

        SystemLogger::dispatch(
            $e->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        $response = response([
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);

        throw new PaymentFailed($e->getMessage(), $e->getCode());

        return $response;
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
