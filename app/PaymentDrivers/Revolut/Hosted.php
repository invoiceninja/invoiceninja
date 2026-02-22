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

namespace App\PaymentDrivers\Revolut;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\RevolutPaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Hosted implements MethodInterface, LivewireMethodInterface
{
    protected RevolutPaymentDriver $revolut;

    public function __construct(RevolutPaymentDriver $revolut)
    {
        $this->revolut = $revolut;

        $this->revolut->init();
    }

    /**
     * Show the authorization page for Revolut (pre-authorization not supported).
     */
    public function authorizeView(array $data): View
    {
        return render('gateways.revolut.hosted.authorize', $data);
    }

    /**
     * Handle the authorization response (redirect only).
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Build payment data: create a Revolut order and store checkout_url.
     */
    public function paymentData(array $data): array
    {
        $amount = (float) $this->revolut->payment_hash->data->amount_with_fee;

        $payload = [
            'amount' => $this->revolut->convertToRevolutAmount($amount),
            'currency' => $this->revolut->client->getCurrencyCode(),
            'description' => ctrans('texts.invoice') . ' - ' . $this->revolut->company_gateway->company->present()->name(),
            'redirect_url' => route('client.payments.response', [
                'company_gateway_id' => $this->revolut->company_gateway->id,
                'payment_hash' => $this->revolut->payment_hash->hash,
                'payment_method_id' => GatewayType::CREDIT_CARD,
            ]),
        ];

        try {
            $response = $this->revolut->httpClient()
                ->post($this->revolut->apiUrl('/api/orders'), [
                    'json' => $payload,
                    'http_errors' => false,
                ]);

            $order = json_decode($response->getBody()->getContents(), true);

            if (!is_array($order)) {
                $order = ['message' => 'Invalid JSON response from Revolut API'];
            }

            if ($response->getStatusCode() >= 400 || empty($order['id'])) {
                $errorMsg = $order['message'] ?? 'Failed to create Revolut order';
                throw new PaymentFailed($errorMsg);
            }

            $this->revolut->payment_hash->withData('order_id', $order['id']);
            $this->revolut->payment_hash->withData('checkout_url', $order['checkout_url']);

            $data['gateway'] = $this->revolut;
            $data['checkout_url'] = $order['checkout_url'];
            $data['order_id'] = $order['id'];
        } catch (\Exception $e) {
            $this->processUnsuccessfulPayment($e);
        }

        return $data;
    }

    /**
     * Show the payment page (redirects user to Revolut hosted checkout).
     */
    public function paymentView(array $data): View
    {
        $data = $this->paymentData($data);

        return render('gateways.revolut.hosted.pay', $data);
    }

    /**
     * Handle the payment response after redirect from Revolut.
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {

        $request->validate([
            'payment_hash' => ['required'],
        ]);

        if (!property_exists($this->revolut->payment_hash->data, 'order_id')) {
            $this->revolut->sendFailureMail('Missing [order_id] property.');

            throw new PaymentFailed('Missing [order_id] property. Please contact the administrator. Reference: ' . $this->revolut->payment_hash->hash);
        }

        $order_id = $this->revolut->payment_hash->data->order_id;

        try {
            $response = $this->revolut->httpClient()
                ->get($this->revolut->apiUrl("/api/orders/{$order_id}"), [
                    'http_errors' => false,
                ]);

            $order = json_decode($response->getBody()->getContents(), true);

            if (!is_array($order)) {
                $order = ['message' => 'Invalid JSON response from Revolut API'];
            }

            if ($response->getStatusCode() >= 400 || empty($order['id'])) {
                $errorMsg = $order['message'] ?? 'Failed to retrieve Revolut order details';
                throw new PaymentFailed($errorMsg);
            }

            if (isset($order['state']) && in_array(strtoupper($order['state']), ['COMPLETED', 'AUTHORISED'])) {
                return $this->processSuccessfulPayment($order);
            }

            throw new PaymentFailed("Payment not completed. Order state: " . ($order['state'] ?? 'unknown'));
        } catch (PaymentFailed $e) {
            return $this->processUnsuccessfulPayment($e);
        } catch (\Exception $e) {
            return $this->processUnsuccessfulPayment($e);
        }
    }

    /**
     * Handle a successful payment: record it and redirect to confirmation.
     */
    public function processSuccessfulPayment(array $order): RedirectResponse
    {

        $data = [
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'amount' => array_sum(array_column($this->revolut->payment_hash->invoices(), 'amount')) + $this->revolut->payment_hash->fee_total,
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'transaction_reference' => $order['id'],
        ];

        $payment_record = $this->revolut->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $order, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_REVOLUT,
            $this->revolut->client,
            $this->revolut->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->revolut->encodePrimaryKey($payment_record->id)]);
    }

    /**
     * Handle an unsuccessful payment: log, notify, throw.
     */
    public function processUnsuccessfulPayment(\Exception $exception): void
    {
        $this->revolut->sendFailureMail($exception->getMessage());

        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_REVOLUT,
            $this->revolut->client,
            $this->revolut->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.revolut.hosted.pay_livewire';
    }
}
