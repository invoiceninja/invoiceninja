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

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mollie\Api\Exceptions\ApiException;

class IDEAL implements MethodInterface, LivewireMethodInterface
{
    protected MolliePaymentDriver $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    /** @inheritDoc */
    public function authorizeView(array $data): View
    {
        return render('gateways.mollie.ideal.authorize', $data);
    }

    /** @inheritDoc */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * @throws \Exception
     * @inheritDoc
     */
    public function paymentView(array $data)
    {
        $this->mollie->payment_hash
            ->withData('gateway_type_id', GatewayType::IDEAL)
            ->withData('client_id', $this->mollie->client->id);

        try {
            $data = [
                'method' => 'ideal',
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $this->mollie->convertToMollieAmount((float) $this->mollie->payment_hash->data->amount_with_fee),
                ],
                'description' => \sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($data['invoices'])->pluck('invoice_number')->toArray())),
                'redirectUrl' => route('client.payments.response', [
                    'company_gateway_id' => $this->mollie->company_gateway->id,
                    'payment_hash' => $this->mollie->payment_hash->hash,
                    'payment_method_id' => GatewayType::IDEAL,
                ]),
                'webhookUrl' => $this->mollie->company_gateway->webhookUrl(),
                'metadata' => [
                    'client_id' => $this->mollie->client->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                    'gateway_type_id' => GatewayType::IDEAL,
                    'payment_type_id' => PaymentType::IDEAL,
                ],
            ];

            if ($this->mollie->company_gateway->token_billing == 'always') {
                // Check if a mollie CustomerId already exists for this client, if so, use that
                $gateway_customer_reference = null;
                if ($this->mollie->client->gateway_tokens->count() > 0) {
                    $gateway_customer_reference = $this->mollie->client->gateway_tokens->first()->gateway_customer_reference;
                } else {
                    // Only if a ClientGatewayToken a.k.a. mandate doesn't exist, we need to set the sequenceType to first to create one
                    $data['sequenceType'] = 'first';
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

                $this->mollie->payment_hash
                    ->withData('mollieCustomerId', $gateway_customer_reference)
                    ->withData('shouldStoreToken', true);
            }

            try {
                $molliePayment = $this->mollie->gateway->payments->create($data);
            } catch (ApiException $e) {
                if (preg_match('/method selected.*not accept recurring payments/', $e->getMessage())){
                    // Can't create mandate, log
                    SystemLogger::dispatch(
                        ['error' => 'Could not create mandate upon iDeal payment. Check if all required payment methods are enabled in Mollie (e.g. SEPA Direct Debit).',
                         'exception' => $e->getMessage()],
                        SystemLog::CATEGORY_GATEWAY_RESPONSE,
                        SystemLog::EVENT_GATEWAY_ERROR,
                        SystemLog::TYPE_MOLLIE,
                        $this->mollie->client,
                        $this->mollie->client->company,
                    );

                    // Try again without creating mandate
                    unset($data['customerId']);
                    unset($data['sequenceType']);
                    $molliePayment = $this->mollie->gateway->payments->create($data);
                } else {
                    // Fail in the usual way
                    throw $e;
                }
            }

            $this->mollie->payment_hash->withData('transaction_reference', $molliePayment->id);

            return redirect($molliePayment->getCheckoutUrl());
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulPayment($exception);
        }
    }

    /**
     * @throws PaymentFailed When the payment fails
     * @inheritDoc
     */
    public function paymentResponse(PaymentResponseRequest $request): \Illuminate\Http\Response|RedirectResponse
    {
        if (! \property_exists($this->mollie->payment_hash->data, 'transaction_reference')) {
            return $this->processUnsuccessfulPayment(
                new PaymentFailed('Whoops, something went wrong. Missing required [transaction_reference] parameter. Please contact administrator. Reference hash: '.$this->mollie->payment_hash->hash)
            );
        }

        try {
            $molliePayment = $this->mollie->gateway->payments->get(
                $this->mollie->payment_hash->data->transaction_reference
            );
        } catch (\Exception) {
            throw new PaymentFailed('Whoops, something went wrong. Could not fetch payment from Mollie. Please contact administrator. Reference hash: '.$this->mollie->payment_hash->hash);
        }

        if ($molliePayment->status === 'paid') {
            return $this->processSuccessfulPayment($molliePayment);
        }

        if ($molliePayment->status === 'open') {
            return $this->processOpenPayment($molliePayment);
        }

        if ($molliePayment->status === 'failed') {
            return $this->processUnsuccessfulPayment(
                new PaymentFailed(ctrans('texts.status_failed'))
            );
        }

        return $this->processUnsuccessfulPayment(
            new PaymentFailed(ctrans('texts.status_voided'))
        );
    }

    /**
     * Handle the successful payment for iDEAL.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment The Mollie payment object
     * @return RedirectResponse
     * @throws ApiException
     */
    public function processSuccessfulPayment(\Mollie\Api\Resources\Payment $molliePayment): RedirectResponse
    {
        $status = MolliePaymentDriver::convertFromMollieStatus($molliePayment->status);

        // Stores a mandate if given
        $this->mollie->createClientGatewayTokenFromMolliePayment($molliePayment);

        /** @var Payment $payment */
        $payment = \App\Models\Payment::withTrashed()
                    ->where('company_id', $this->mollie->client->company_id)
                    ->where('transaction_reference', $molliePayment->id)
                    ->first();

        if (!$payment) {
            // Create payment if it does not exist yet
            $data = [
                'gateway_type_id' => GatewayType::IDEAL,
                'amount' => array_sum(array_column($this->mollie->payment_hash->invoices(), 'amount')) + $this->mollie->payment_hash->fee_total,
                'payment_type' => PaymentType::IDEAL,
                'transaction_reference' => $molliePayment->id,
            ];

            $payment = $this->mollie->createPayment($data, $status);
        } else {
            // Else just update the status
            $payment->status_id = $status;
            $payment->save();
        }

        SystemLogger::dispatch(
            ['mollie_payment' => $molliePayment, 'payment' => $payment],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
    }

    /**
     * Handle 'open' payment status for iDEAL.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment The Mollie payment object
     * @return RedirectResponse
     * @throws ApiException
     */
    public function processOpenPayment(\Mollie\Api\Resources\Payment $molliePayment): RedirectResponse
    {
        return $this->processSuccessfulPayment($molliePayment);
    }

    /**
     * Handle unsuccessful payment.
     *
     * @param \Exception $exception The exception that was thrown
     * @throws PaymentFailed When the payment fails
     * @return \Illuminate\Http\Response
     */
    public function processUnsuccessfulPayment(\Exception $exception): \Illuminate\Http\Response
    {
        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        $response = response([
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ]);

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());

        return $response;
    }

    /** @inheritDoc */
    public function livewirePaymentView(array $data): string
    {
        // Doesn't support, it's offsite payment method.

        return '';
    }

    /** @inheritDoc */
    public function paymentData(array $data): array
    {
        $this->paymentView($data);

        return $data;
    }
}
