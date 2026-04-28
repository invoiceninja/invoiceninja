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

namespace App\PaymentDrivers\Payware;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\PaywarePaymentDriver;
use Illuminate\Http\Request;

class MobilePayment implements MethodInterface, LivewireMethodInterface
{
    public function __construct(protected PaywarePaymentDriver $driver)
    {
        $this->driver->init();
    }

    public function authorizeView(array $data)
    {
        // payware does not support payment method authorization/tokenization
    }

    public function authorizeResponse(Request $request)
    {
        // payware does not support payment method authorization/tokenization
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.payware.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $paymentHash = PaymentHash::where('hash', $request->payment_hash)->first();

        if (!$paymentHash) {
            throw new PaymentFailed('payware: Payment hash not found.');
        }

        $data = (array) $paymentHash->data;
        $status = $data['payware_status'] ?? 'ACTIVE';

        if ($status === 'CONFIRMED' && isset($data['payware_payment_id'])) {
            return redirect()->route('client.payments.show', [
                'payment' => $data['payware_payment_id'],
            ]);
        }

        throw new PaymentFailed('payware: Payment was not confirmed.');
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.payware.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->driver;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['currency'] = $this->driver->client->getCurrencyCode();

        try {
            $api = $this->driver->getApi();

            $callbackUrl = $this->driver->genericWebhookUrl();

            $invoice = collect($this->driver->payment_hash->invoices())->first();
            $companyName = $this->driver->client->company->present()->name();
            $reason = $companyName . ', order #' . ($invoice->invoice_number ?? '');

            $timeToLive = (int) ($this->driver->company_gateway->getConfigField('timeToLive') ?: 600);
            $currencyPrecision = (int) ($this->driver->client->currency()->precision ?? 2);

            $result = $api->createTransaction(
                (float) $data['amount'],
                $data['currency'],
                $reason,
                $callbackUrl,
                $this->driver->payment_hash->hash,
                $timeToLive,
                $currencyPrecision,
            );

            // Store transaction data in payment_hash for webhook and polling
            $hashData = (array) $this->driver->payment_hash->data;
            $hashData['payware_transaction_id'] = $result['transactionId'];
            $hashData['payware_status'] = 'ACTIVE';
            $hashData['payware_created_at'] = time();
            $this->driver->payment_hash->data = $hashData;
            $this->driver->payment_hash->save();

            $data['transaction_id'] = $result['transactionId'];
            $data['time_to_live'] = $timeToLive;

        } catch (\Exception $e) {
            // Surface the technical detail to admins via SystemLog (visible in
            // gateway logs view), but never to the paying customer.
            SystemLogger::dispatch(
                ['error' => $e->getMessage(), 'context' => 'createTransaction'],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYWARE,
                $this->driver->client,
                $this->driver->client->company,
            );

            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'));
        }

        return $data;
    }
}
