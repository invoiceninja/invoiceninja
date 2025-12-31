<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mollie\Api\Resources\Payment as ResourcesPayment;

class BankTransfer implements MethodInterface, LivewireMethodInterface
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
        return render('gateways.mollie.bank_transfer.authorize', $data);
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
            ->withData('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->withData('client_id', $this->mollie->client->id);

        try {
            $payment = $this->mollie->gateway->payments->create([
                'method' => 'banktransfer',
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $this->mollie->convertToMollieAmount((float) $this->mollie->payment_hash->data->amount_with_fee),
                ],
                'description' => \sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($data['invoices'])->pluck('invoice_number')->toArray())),
                'redirectUrl' => route('client.payments.response', [
                    'company_gateway_id' => $this->mollie->company_gateway->id,
                    'payment_hash' => $this->mollie->payment_hash->hash,
                    'payment_method_id' => GatewayType::BANK_TRANSFER,
                ]),
                'webhookUrl' => $this->mollie->company_gateway->webhookUrl(),
                'metadata' => [
                    'client_id' => $this->mollie->client->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                    'gateway_type_id' => GatewayType::BANK_TRANSFER,
                    'payment_type_id' => PaymentType::MOLLIE_BANK_TRANSFER,
                ],
            ]);

            $this->mollie->payment_hash->withData('transaction_reference', $payment->id);

            return redirect($payment->getCheckoutUrl());
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

            if ($molliePayment->status === 'paid') {
                return $this->processSuccessfulPayment($molliePayment);
            }

            if ($molliePayment->status === 'open') {
                return $this->processOpenPayment($molliePayment);
            }

            return $this->processUnsuccessfulPayment(
                new PaymentFailed(ctrans('texts.status_voided'))
            );
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulPayment($exception);
        }
    }

    /**
     * Handle the successful payment for bank transfer.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment The Mollie payment object
     * @param string $status The payment status (default: 'paid')
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSuccessfulPayment(ResourcesPayment $molliePayment, $status = 'paid'): RedirectResponse
    {
        $data = [
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
            'amount' => array_sum(array_column($this->mollie->payment_hash->invoices(), 'amount')) + $this->mollie->payment_hash->fee_total,
            'payment_type' => PaymentType::MOLLIE_BANK_TRANSFER,
            'transaction_reference' => $molliePayment->id,
        ];

        $payment_record = $this->mollie->createPayment(
            $data,
            $status === 'paid' ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING
        );

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
     * Handle 'open' payment status for bank transfer.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment The Mollie payment object
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processOpenPayment(ResourcesPayment $molliePayment): RedirectResponse
    {
        return $this->processSuccessfulPayment($molliePayment, 'open');
    }

    /**
     * Handle unsuccessful payment.
     *
     * @param \Exception $e The exception that was thrown
     * @throws PaymentFailed When the payment fails
     * @return \Illuminate\Http\Response
     */
    public function processUnsuccessfulPayment(Exception $e): \Illuminate\Http\Response
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
