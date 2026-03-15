<?php

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\Payment;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class MolliePaymentMethod implements MethodInterface, LivewireMethodInterface
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

    /**
     * @inheritDoc
     */
    public function authorizeView(array $data): View
    {
        return render(static::AUTHORIZE_VIEW_TEMPLATE, $data);
    }

    /**
     * Process the response from the authorization page.
     *
     * @param Request $request
     * @return RedirectResponse
     */
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
            ->withData('gateway_type_id', static::GATEWAY_TYPE_ID)
            ->withData('client_id', $this->mollie->client->id);

        try {
            $payment = $this->mollie->gateway->payments->create([
                'method' => static::MOLLIE_PAYMENT_METHOD,
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $this->mollie->convertToMollieAmount((float)$this->mollie->payment_hash->data->amount_with_fee),
                ],
                'description' => \sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($data['invoices'])->pluck('invoice_number')->toArray())),
                'redirectUrl' => route('client.payments.response', [
                    'company_gateway_id' => $this->mollie->company_gateway->id,
                    'payment_hash' => $this->mollie->payment_hash->hash,
                    'payment_method_id' => static::GATEWAY_TYPE_ID,
                ]),
                'webhookUrl' => $this->mollie->company_gateway->webhookUrl(),
                'metadata' => [
                    'client_id' => $this->mollie->client->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                    'gateway_type_id' => static::GATEWAY_TYPE_ID,
                    'payment_type_id' => static::PAYMENT_TYPE_ID,
                ],
            ]);

            $this->mollie->payment_hash->withData('transaction_reference', $payment->id);

            return redirect($payment->getCheckoutUrl());
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulPayment($exception);
        }
    }

    /** @inheritDoc */
    public function paymentResponse(PaymentResponseRequest $request): \Illuminate\Http\Response|RedirectResponse
    {
        if (!$this->mollie?->payment_hash?->data?->transaction_reference) {
            return $this->processUnsuccessfulPayment(
                new PaymentFailed('Whoops, something went wrong. Missing required [transaction_reference] parameter. Please contact administrator. Reference hash: ' . $this->mollie->payment_hash->hash)
            );
        }

        try {
            $molliePayment = $this->mollie->gateway->payments->get(
                $this->mollie->payment_hash->data->transaction_reference
            );
        } catch (\Exception) {
            throw new PaymentFailed('Whoops, something went wrong. Could not fetch payment from Mollie. Please contact administrator. Reference hash: ' . $this->mollie->payment_hash->hash);
        }

        if (in_array($molliePayment->status, ['paid', 'open'])) {
            return $this->processSuccessfulPayment($molliePayment);
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
     * Process a successful payment.
     *
     * @param \Mollie\Api\Resources\Payment $molliePayment The Mollie payment object
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function processSuccessfulPayment(\Mollie\Api\Resources\Payment $molliePayment): \Illuminate\Http\RedirectResponse
    {
        // InvoiceNinja Payments are solely created and updated in the webhook, to prevent race-conditions.
        // The webhook is called before the Client is sent back to InvoiceNinja,
        // Since we want to wait for payment creation, we wait here till it exists or we timeout.
        $timeout = 10; // 10 seconds timeout
        $start_time = time();

        $payment = null;

        while (!$payment && (time() - $start_time) < $timeout) {
            usleep(500000); // Sleep for 500ms

            /** @var Payment $payment */
            $payment = \App\Models\Payment::withTrashed()
                ->where('company_id', $this->mollie->client->company_id)
                ->where('transaction_reference', $molliePayment->id)
                ->first();
        }

        if (!$payment) {
            $this->mollie->logUnsuccessfulGatewayResponse(
                ['molliePayment' => $molliePayment, 'data' => $this->mollie->payment_hash->data],
                SystemLog::TYPE_MOLLIE
            );
            throw new \Exception("Timeout after {$timeout} seconds. Transaction reference: {$molliePayment->id}");
        }

        $this->mollie->logSuccessfulGatewayResponse(
            ['molliePayment' => $molliePayment, 'payment' => $payment, 'data' => $this->mollie->payment_hash->data],
            SystemLog::TYPE_MOLLIE
        );

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
    }

    /**
     * Handle an unsuccessful payment attempt.
     *
     * @param \Throwable|string $e The exception that was thrown
     * @throws PaymentFailed Always throws a PaymentFailed exception
     * @return \Illuminate\Http\Response
     */
    public function processUnsuccessfulPayment(\Throwable|string $e): \Illuminate\Http\Response
    {
        SystemLogger::dispatch(
            is_string($e) ? $e : $e->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        throw new PaymentFailed(is_string($e) ? $e : $e->getMessage(), is_string($e) ? 400 : $e->getCode());

        return response([
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
    }

    /**
     * Handle livewire payment view for offsite payment methods.
     *
     * @param array $data
     * @return string
     */
    public function livewirePaymentView(array $data): string
    {
        // Doesn't support, it's offsite payment method.
        return '';
    }

    /**
     * Prepare payment data for offsite payment methods.
     *
     * @param array $data
     * @return array
     */
    public function paymentData(array $data): array
    {
        return [];
    }
}
